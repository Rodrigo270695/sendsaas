<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\TenantWhatsappSession;
use App\Support\OpenWa\OpenWaWebhookEvents;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alinea un único webhook de bandeja por sesión OpenWA.
 * OpenWA limita a 16 webhooks/sesión: no se debe hacer POST en cada sync.
 */
final class TenantWhatsappWebhookRegistrar
{
    public function __construct(
        private readonly OpenWaClient $client,
    ) {}

    public function ensureForSession(TenantWhatsappSession $session, bool $throw = false): void
    {
        if (! $this->client->isConfigured() || ! $session->isReady()) {
            if ($throw) {
                throw new \RuntimeException('OpenWA no está configurado o la sesión no está lista.');
            }

            return;
        }

        $session->loadMissing('tenant');
        $url = $this->inboxUrl((string) ($session->tenant?->slug ?? ''));
        if ($url === '') {
            if ($throw) {
                throw new \RuntimeException('No se pudo armar la URL del webhook de bandeja.');
            }

            return;
        }

        $sessionId = (string) $session->openwa_session_id;
        if ($sessionId === '') {
            if ($throw) {
                throw new \RuntimeException('La sesión no tiene openwa_session_id.');
            }

            return;
        }

        $secret = trim((string) config('openwa.webhook_secret', ''));

        try {
            $this->align($session, $sessionId, $url, $secret);
        } catch (Throwable $e) {
            Log::warning('OpenWA: no se pudo registrar webhook de bandeja', [
                'session_id' => $sessionId,
                'tenant_id' => $session->tenant_id,
                'error' => $e->getMessage(),
            ]);

            if ($throw) {
                throw $e;
            }
        }
    }

    public function ensureForSessionOrFail(TenantWhatsappSession $session): void
    {
        $this->ensureForSession($session, throw: true);
    }

    /**
     * @return list<array{id: string, url: string, events: mixed, active: mixed}>
     */
    public function inboxHooks(TenantWhatsappSession $session): array
    {
        $sessionId = (string) $session->openwa_session_id;
        if ($sessionId === '') {
            return [];
        }

        $out = [];
        foreach ($this->webhooksForSession($sessionId) as $hook) {
            $url = (string) ($hook['url'] ?? '');
            if (! $this->isInboxUrl($url)) {
                continue;
            }

            $out[] = [
                'id' => (string) ($hook['id'] ?? ''),
                'url' => $url,
                'events' => $hook['events'] ?? null,
                'active' => $hook['active'] ?? $hook['enabled'] ?? null,
            ];
        }

        return $out;
    }

    public function inboxUrl(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $override = rtrim(trim((string) config('openwa.webhook_base_url', '')), '/');
        if ($override !== '') {
            return $override.'/'.$slug;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' ? $appUrl.'/api/webhooks/openwa/'.$slug : '';
    }

    private function align(TenantWhatsappSession $session, string $sessionId, string $url, string $secret): void
    {
        $hooks = $this->webhooksForSession($sessionId);
        $ours = [];
        $others = [];

        foreach ($hooks as $hook) {
            if ($this->isInboxUrl((string) ($hook['url'] ?? ''))) {
                $ours[] = $hook;
            } else {
                $others[] = $hook;
            }
        }

        $kept = $ours !== [] ? array_shift($ours) : null;
        foreach ($ours as $dup) {
            $this->tryDelete($sessionId, $dup);
        }
        $this->deleteDuplicateUrls($sessionId, $others);

        if ($kept !== null) {
            $webhookId = (string) ($kept['id'] ?? '');
            if ($webhookId === '') {
                throw new \RuntimeException('Webhook de bandeja sin id.');
            }

            $this->client->updateWebhook($sessionId, $webhookId, $this->webhookPayload($url, $secret));

            return;
        }

        $hooks = $this->webhooksForSession($sessionId);
        if (count($hooks) >= 16) {
            Log::warning('OpenWA: sesión llena (16 webhooks) y no hay webhook de bandeja', [
                'session_id' => $sessionId,
                'tenant_id' => $session->tenant_id,
            ]);

            return;
        }

        $this->client->registerWebhook(
            $sessionId,
            $url,
            $secret !== '' ? $secret : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function webhooksForSession(string $sessionId): array
    {
        $listed = $this->client->listWebhooks($sessionId);
        $out = [];
        foreach ($listed as $hook) {
            if (is_array($hook) && (string) ($hook['id'] ?? '') !== '') {
                $out[] = $hook;
            }
        }

        if ($out !== []) {
            return $out;
        }

        foreach ($this->client->listAllWebhooks() as $hook) {
            if (! is_array($hook)) {
                continue;
            }
            $hookSession = (string) ($hook['sessionId'] ?? $hook['session_id'] ?? '');
            if ($hookSession === $sessionId && (string) ($hook['id'] ?? '') !== '') {
                $out[] = $hook;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $hooks
     */
    private function deleteDuplicateUrls(string $sessionId, array $hooks): void
    {
        $byUrl = [];
        foreach ($hooks as $hook) {
            $key = strtolower(trim((string) ($hook['url'] ?? '')));
            if ($key === '') {
                $key = '_empty_'.(string) ($hook['id'] ?? uniqid());
            }
            $byUrl[$key][] = $hook;
        }

        foreach ($byUrl as $list) {
            array_shift($list);
            foreach ($list as $dup) {
                $this->tryDelete($sessionId, $dup);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $hook
     */
    private function tryDelete(string $sessionId, array $hook): void
    {
        $id = (string) ($hook['id'] ?? '');
        if ($id === '') {
            return;
        }

        try {
            $this->client->deleteWebhook($sessionId, $id);
        } catch (Throwable $e) {
            Log::warning('OpenWA: no se pudo borrar webhook duplicado', [
                'session_id' => $sessionId,
                'webhook_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(string $url, string $secret): array
    {
        $payload = [
            'url' => $url,
            'events' => OpenWaWebhookEvents::inboundMessageSubscriptions(),
            'active' => true,
        ];
        if ($secret !== '') {
            $payload['secret'] = $secret;
            $payload['headers'] = [
                'X-Webhook-Secret' => $secret,
            ];
        }

        return $payload;
    }

    private function isInboxUrl(string $url): bool
    {
        return str_contains($url, '/api/webhooks/openwa/');
    }
}
