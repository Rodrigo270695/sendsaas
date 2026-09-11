<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Exceptions\AgentReplyException;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TenantWhatsappSession;
use App\Models\User;
use App\Services\Billing\OutboundDailyQuota;
use App\Services\OpenWa\OpenWaClient;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AgentReplyService
{
    public function __construct(
        private readonly OpenWaClient $openWa,
        private readonly OutboundDailyQuota $quota,
    ) {}

    public function send(Conversation $conversation, string $body, User $agent): ConversationMessage
    {
        $tenant = current_tenant();
        if ($tenant === null) {
            throw new AgentReplyException('Solo usuarios de una empresa pueden responder.');
        }

        $conversation->loadMissing('contact');

        $body = trim($body);
        if ($body === '') {
            throw new AgentReplyException('Escribe un mensaje.');
        }

        if (! $this->openWa->isConfigured()) {
            throw new AgentReplyException('WhatsApp no está configurado en el servidor.');
        }

        if (Cache::has('openwa:cooldown')) {
            throw new AgentReplyException('WhatsApp está en pausa por límite del proveedor. Reintenta en unos minutos.');
        }

        if ($this->quota->wouldExceed($tenant)) {
            throw new AgentReplyException(PlanLimits::message($tenant->plan, 'max_outbound_per_day'));
        }

        $session = $this->resolveReadySession($conversation, (string) $tenant->id);
        $chatId = $this->resolveChatId($conversation);

        try {
            $remote = $this->openWa->sendTextWithDeliveryFallback(
                (string) $session->openwa_session_id,
                $chatId,
                $body,
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'OpenWA HTTP 429')) {
                Cache::put(
                    'openwa:cooldown',
                    1,
                    now()->addSeconds((int) config('openwa.cooldown_seconds', 240)),
                );

                throw new AgentReplyException('WhatsApp está en pausa por límite del proveedor. Reintenta en unos minutos.');
            }

            throw new AgentReplyException('No se pudo enviar el mensaje. Inténtalo de nuevo.');
        }

        $externalId = trim((string) ($remote['messageId'] ?? $remote['id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'out:'.Str::uuid()->toString();
        }

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => ConversationMessage::SENDER_AGENT,
            'sender_id' => $agent->id,
            'external_id' => $externalId,
            'direction' => ConversationMessage::DIRECTION_OUT,
            'message_type' => 'text',
            'body' => $body,
            'status' => ConversationMessage::STATUS_SENT,
            'sent_at' => now(),
            'metadata' => [
                'wa_chat_id' => $chatId,
                'openwa_session_id' => $session->openwa_session_id,
                'assumed_delivery' => (bool) ($remote['assumed_delivery'] ?? false),
            ],
        ]);

        $conversation->forceFill([
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
            'wa_chat_id' => $conversation->wa_chat_id ?: $chatId,
            'whatsapp_session_id' => $session->id,
        ])->save();

        $conversation->contact?->forceFill(['last_contact_at' => now()])->save();

        $this->quota->increment($tenant);

        return $message;
    }

    private function resolveReadySession(Conversation $conversation, string $tenantId): TenantWhatsappSession
    {
        if ($conversation->whatsapp_session_id) {
            $linked = TenantWhatsappSession::query()
                ->whereKey($conversation->whatsapp_session_id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($linked?->isReady() && filled($linked->openwa_session_id)) {
                return $linked;
            }
        }

        $ready = TenantWhatsappSession::query()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantWhatsappSession::STATUS_CONNECTED)
            ->whereNotNull('openwa_session_id')
            ->first();

        if ($ready === null) {
            throw new AgentReplyException('No hay un WhatsApp conectado. Vincula una sesión para responder.');
        }

        return $ready;
    }

    private function resolveChatId(Conversation $conversation): string
    {
        $chatId = trim((string) ($conversation->wa_chat_id ?? ''));
        if ($chatId !== '') {
            return $chatId;
        }

        $phone = trim((string) ($conversation->contact?->phone ?? ''));
        if ($phone === '') {
            throw new AgentReplyException('Esta conversación no tiene un número de WhatsApp.');
        }

        return $phone.'@c.us';
    }
}
