<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\TenantWhatsappSession;
use Illuminate\Support\Carbon;
use RuntimeException;

final class WhatsappSessionLinker
{
    public function __construct(private readonly OpenWaClient $client) {}

    public function ensureRemote(TenantWhatsappSession $session, bool $wake = true): TenantWhatsappSession
    {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('OpenWA no está configurado en el servidor.');
        }

        $name = $session->openwa_session_name;
        $remote = $this->client->findSessionByName($name)
            ?? $this->client->createSession($name);

        $sessionId = (string) ($remote['id'] ?? $session->openwa_session_id ?? '');
        if ($sessionId === '') {
            throw new RuntimeException('OpenWA no devolvió un id de sesión.');
        }

        $status = (string) ($remote['status'] ?? $session->status);
        $lastError = null;

        if ($wake) {
            $reconnect = $this->client->tryStartIfDown($sessionId, $status);
            if ($reconnect['remote'] !== null) {
                $remote = $reconnect['remote'];
                $status = (string) ($remote['status'] ?? $status);
            } elseif ($reconnect['attempted'] && is_string($reconnect['error']) && $reconnect['error'] !== '') {
                $lastError = $reconnect['error'];
            }
        }

        $this->applyRemote($session, $remote, $sessionId, $lastError);

        return $session->fresh() ?? $session;
    }

    public function refresh(TenantWhatsappSession $session): TenantWhatsappSession
    {
        $sessionId = (string) $session->openwa_session_id;
        if ($sessionId === '') {
            return $this->ensureRemote($session, wake: false);
        }

        $remote = $this->client->getSession($sessionId);
        $this->applyRemote($session, $remote, $sessionId, null);

        return $session->fresh() ?? $session;
    }

    public function disconnect(TenantWhatsappSession $session): TenantWhatsappSession
    {
        $sessionId = (string) $session->openwa_session_id;
        if ($sessionId !== '') {
            $this->client->stopSession($sessionId);
            try {
                $remote = $this->client->getSession($sessionId);
                $status = (string) ($remote['status'] ?? 'disconnected');
            } catch (\Throwable) {
                $status = 'disconnected';
            }
        } else {
            $status = 'disconnected';
        }

        $session->forceFill([
            'status' => $status,
            'phone' => null,
            'push_name' => null,
            'connected_at' => null,
            'auto_reconnect' => false,
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        return $session->fresh() ?? $session;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function applyRemote(
        TenantWhatsappSession $session,
        array $remote,
        string $sessionId,
        ?string $lastError,
    ): void {
        $session->forceFill([
            'openwa_session_id' => $sessionId,
            'openwa_session_name' => (string) ($remote['name'] ?? $session->openwa_session_name),
            'status' => (string) ($remote['status'] ?? $session->status),
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : $session->phone,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : $session->push_name,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse($remote['connectedAt'])
                : $session->connected_at,
            'last_synced_at' => now(),
            'last_error' => $lastError,
        ])->save();
    }
}
