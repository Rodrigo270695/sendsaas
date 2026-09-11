<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use App\Services\OpenWa\OpenWaClient;
use App\Support\OpenWa\OpenWaInboundPayload;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OpenWaInboxSynchronizer
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly InboundMessageIngestor $ingestor,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return array{ingested: int, skipped: int, errors: int}
     */
    public function syncSession(TenantWhatsappSession $session, int $limit = 40): array
    {
        $stats = ['ingested' => 0, 'skipped' => 0, 'errors' => 0];
        $remoteId = (string) $session->openwa_session_id;
        if ($remoteId === '' || ! $session->isReady() || ! $this->client->isConfigured()) {
            return $stats;
        }

        $tenant = $session->tenant ?? Tenant::query()->find($session->tenant_id);
        if ($tenant === null || $tenant->slug === null || $tenant->slug === '') {
            return $stats;
        }

        try {
            $rows = $this->client->listRecentMessages($remoteId, $limit);
        } catch (Throwable $e) {
            Log::warning('OpenWA sync: no se pudieron listar mensajes', [
                'session_id' => $remoteId,
                'error' => $e->getMessage(),
            ]);

            return $stats;
        }

        $this->tenants->resolveBySlug((string) $tenant->slug);

        try {
            foreach ($rows as $row) {
                if (! $this->isIncomingPersonal($row)) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $payload = OpenWaInboundPayload::fromRequest([
                        'event' => 'message.received',
                        'sessionId' => $remoteId,
                        'data' => $this->toWebhookData($row, $remoteId),
                    ]);

                    if ($payload->phone === null || $payload->isGroup()) {
                        $stats['skipped']++;

                        continue;
                    }

                    $result = $this->ingestor->ingest($payload, $session);
                    if ($result['ingested']) {
                        $stats['ingested']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    Log::warning('OpenWA sync: no se pudo ingerir un mensaje', [
                        'session_id' => $remoteId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $this->tenants->forget();
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isIncomingPersonal(array $row): bool
    {
        $direction = strtolower((string) ($row['direction'] ?? ''));
        if ($direction === 'outgoing' || (bool) ($row['fromMe'] ?? false)) {
            return false;
        }

        $chatId = (string) ($row['chatId'] ?? $row['from'] ?? '');
        if (str_ends_with($chatId, '@g.us') || str_ends_with($chatId, '@newsletter')) {
            return false;
        }

        return $direction === 'incoming' || $direction === '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toWebhookData(array $row, string $sessionId): array
    {
        return [
            'id' => (string) ($row['waMessageId'] ?? $row['id'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'from' => (string) ($row['from'] ?? $row['chatId'] ?? ''),
            'chatId' => (string) ($row['chatId'] ?? $row['from'] ?? ''),
            'fromMe' => false,
            'direction' => 'incoming',
            'type' => (string) ($row['type'] ?? 'text'),
            'pushName' => $row['chatName'] ?? null,
            'sessionId' => $sessionId,
        ];
    }
}
