<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\OutboundQueueItem;
use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use App\Services\Billing\OutboundDailyQuota;
use App\Services\OpenWa\OpenWaClient;
use App\Support\Outbound\DripPacing;
use App\Support\Outbound\SendWindow;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OutboundDripDispatcher
{
    public function __construct(
        private readonly OpenWaClient $openWa,
        private readonly OutboundDailyQuota $quota,
    ) {}

    /**
     * @return array{processed: int, sent: int, failed: int, skipped: string|null}
     */
    public function processTenant(Tenant $tenant, int $limit = 1): array
    {
        $empty = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => null];

        if (! (bool) config('outbound.drip_enabled', true)) {
            return [...$empty, 'skipped' => 'disabled'];
        }

        if (! $this->openWa->isConfigured()) {
            return [...$empty, 'skipped' => 'openwa_unconfigured'];
        }

        if (Cache::has('openwa:cooldown')) {
            return [...$empty, 'skipped' => 'openwa_cooldown'];
        }

        $tenant->loadMissing('plan');

        if (! SendWindow::isOpen($tenant)) {
            return [...$empty, 'skipped' => 'outside_window'];
        }

        if ($this->quota->wouldExceed($tenant)) {
            return [...$empty, 'skipped' => 'quota_exhausted'];
        }

        if (! DripPacing::isReady($tenant)) {
            return [...$empty, 'skipped' => 'interval'];
        }

        if ($this->hourlyCapReached($tenant)) {
            return [...$empty, 'skipped' => 'hourly_cap'];
        }

        if ($this->readySession($tenant) === null) {
            return [...$empty, 'skipped' => 'no_session'];
        }

        $this->releaseStaleReservations();

        $sent = 0;
        $failed = 0;
        $processed = 0;

        for ($i = 0; $i < max(1, $limit); $i++) {
            if ($this->quota->wouldExceed($tenant) || ! DripPacing::isReady($tenant)) {
                break;
            }

            $item = $this->reserveNext();
            if ($item === null) {
                break;
            }

            $processed++;
            $result = $this->dispatch($tenant, $item);
            if ($result === 'sent') {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $processed === 0 ? 'empty' : null,
        ];
    }

    private function dispatch(Tenant $tenant, OutboundQueueItem $item): string
    {
        $session = $this->readySession($tenant, $item->whatsapp_session_id);
        if ($session === null) {
            $this->requeueOrFail($item, 'No hay un WhatsApp conectado.');

            return 'failed';
        }

        $gap = max(1, (int) config('outbound.openwa_gap_seconds', 3));
        if (! Cache::add('openwa:send-text', 1, now()->addSeconds($gap + 20))) {
            $this->releaseReservation($item);

            return 'failed';
        }

        try {
            if (Cache::has('openwa:send-gap')) {
                $this->releaseReservation($item);

                return 'failed';
            }

            try {
                $remote = $this->openWa->sendTextWithDeliveryFallback(
                    (string) $session->openwa_session_id,
                    $item->chat_id,
                    $item->body,
                );
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'OpenWA HTTP 429')) {
                    Cache::put(
                        'openwa:cooldown',
                        1,
                        now()->addSeconds((int) config('openwa.cooldown_seconds', 240)),
                    );
                    $this->releaseReservation($item, 'OpenWA 429');

                    return 'failed';
                }

                $this->requeueOrFail($item, $e->getMessage());

                return 'failed';
            }

            Cache::put('openwa:send-gap', 1, now()->addSeconds($gap));

            $this->markSent($tenant, $item, $session, $remote);

            return 'sent';
        } finally {
            Cache::forget('openwa:send-text');
        }
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function markSent(
        Tenant $tenant,
        OutboundQueueItem $item,
        TenantWhatsappSession $session,
        array $remote,
    ): void {
        $conversation = $this->resolveConversation($item, $session);

        $externalId = trim((string) ($remote['messageId'] ?? $remote['id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'out:'.Str::uuid()->toString();
        }

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => ConversationMessage::SENDER_SYSTEM,
            'sender_id' => $item->created_by_id,
            'external_id' => $externalId,
            'direction' => ConversationMessage::DIRECTION_OUT,
            'message_type' => $item->message_type ?: 'text',
            'body' => $item->body,
            'status' => ConversationMessage::STATUS_SENT,
            'sent_at' => now(),
            'metadata' => [
                'outbound_queue_id' => $item->id,
                'kind' => $item->kind,
                'wa_chat_id' => $item->chat_id,
                'openwa_session_id' => $session->openwa_session_id,
                'assumed_delivery' => (bool) ($remote['assumed_delivery'] ?? false),
            ],
        ]);

        $conversation->forceFill([
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
            'wa_chat_id' => $conversation->wa_chat_id ?: $item->chat_id,
            'whatsapp_session_id' => $session->id,
        ])->save();

        $conversation->contact?->forceFill(['last_contact_at' => now()])->save();

        $item->forceFill([
            'status' => OutboundQueueItem::STATUS_SENT,
            'conversation_id' => $conversation->id,
            'whatsapp_session_id' => $session->id,
            'sent_at' => now(),
            'reserved_at' => null,
            'last_error' => null,
        ])->save();

        $this->quota->increment($tenant);
        $remaining = max(0, (PlanLimits::intLimit($tenant->plan, 'max_outbound_per_day') ?? 0) - $this->quota->usedToday($tenant));
        if (PlanLimits::intLimit($tenant->plan, 'max_outbound_per_day') === null) {
            $remaining = 9999;
        }
        DripPacing::markSent($tenant, max(1, $remaining));
    }

    private function resolveConversation(OutboundQueueItem $item, TenantWhatsappSession $session): Conversation
    {
        if ($item->conversation_id) {
            $existing = Conversation::query()->whereKey($item->conversation_id)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $contactId = $item->contact_id;
        if ($contactId === null) {
            $contact = Contact::query()->where('phone', $item->contact_phone)->first();
            $contactId = $contact?->id;
        }

        if ($contactId === null) {
            $contact = Contact::query()->create([
                'name' => $item->contact_phone,
                'phone' => $item->contact_phone,
            ]);
            $contactId = $contact->id;
            $item->forceFill(['contact_id' => $contactId])->save();
        }

        $conversation = Conversation::query()
            ->where('contact_id', $contactId)
            ->where('channel', Conversation::CHANNEL_WHATSAPP)
            ->first();

        if ($conversation !== null) {
            return $conversation;
        }

        return Conversation::query()->create([
            'contact_id' => $contactId,
            'channel' => Conversation::CHANNEL_WHATSAPP,
            'whatsapp_session_id' => $session->id,
            'wa_chat_id' => $item->chat_id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);
    }

    private function reserveNext(): ?OutboundQueueItem
    {
        return DB::transaction(function (): ?OutboundQueueItem {
            $item = OutboundQueueItem::query()
                ->where('status', OutboundQueueItem::STATUS_QUEUED)
                ->where('priority', OutboundQueueItem::PRIORITY_DRIP)
                ->where('available_at', '<=', now())
                ->orderBy('available_at')
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                return null;
            }

            $item->forceFill([
                'status' => OutboundQueueItem::STATUS_RESERVED,
                'reserved_at' => now(),
                'attempts' => $item->attempts + 1,
            ])->save();

            return $item;
        });
    }

    private function releaseStaleReservations(): void
    {
        $timeout = max(30, (int) config('outbound.reserve_timeout_seconds', 120));

        OutboundQueueItem::query()
            ->where('status', OutboundQueueItem::STATUS_RESERVED)
            ->where('reserved_at', '<', now()->subSeconds($timeout))
            ->update([
                'status' => OutboundQueueItem::STATUS_QUEUED,
                'reserved_at' => null,
            ]);
    }

    private function releaseReservation(OutboundQueueItem $item, ?string $error = null): void
    {
        $item->forceFill([
            'status' => OutboundQueueItem::STATUS_QUEUED,
            'reserved_at' => null,
            'last_error' => $error,
            'attempts' => max(0, $item->attempts - 1),
        ])->save();
    }

    private function requeueOrFail(OutboundQueueItem $item, string $error): void
    {
        $max = max(1, (int) config('outbound.max_attempts', 5));
        $failed = $item->attempts >= $max;

        $item->forceFill([
            'status' => $failed ? OutboundQueueItem::STATUS_FAILED : OutboundQueueItem::STATUS_QUEUED,
            'reserved_at' => null,
            'last_error' => $error,
            'available_at' => $failed
                ? $item->available_at
                : now()->addSeconds((int) config('outbound.retry_delay_seconds', 120)),
        ])->save();
    }

    private function readySession(Tenant $tenant, ?string $preferredId = null): ?TenantWhatsappSession
    {
        if ($preferredId) {
            $linked = TenantWhatsappSession::query()
                ->whereKey($preferredId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($linked?->isReady() && filled($linked->openwa_session_id)) {
                return $linked;
            }
        }

        return TenantWhatsappSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantWhatsappSession::STATUS_CONNECTED)
            ->whereNotNull('openwa_session_id')
            ->first();
    }

    private function hourlyCapReached(Tenant $tenant): bool
    {
        $cap = DripPacing::hourlyCap($tenant);
        if ($cap === null) {
            return false;
        }

        $sent = OutboundQueueItem::query()
            ->where('status', OutboundQueueItem::STATUS_SENT)
            ->where('priority', OutboundQueueItem::PRIORITY_DRIP)
            ->where('sent_at', '>=', now(SendWindow::timezone($tenant))->startOfHour())
            ->count();

        return $sent >= $cap;
    }
}
