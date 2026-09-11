<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Support\OpenWa\OpenWaWebhookEvents;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class OpenWaClient
{
    public function isConfigured(): bool
    {
        return (bool) config('openwa.enabled')
            && trim((string) config('openwa.api_key', '')) !== ''
            && trim((string) config('openwa.api_url', '')) !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(): array
    {
        $response = $this->request('get', '/api/sessions');

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSessionByName(string $name): ?array
    {
        foreach ($this->listSessions() as $session) {
            if (is_array($session) && ($session['name'] ?? null) === $name) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(string $name): array
    {
        $response = $this->request('post', '/api/sessions', [
            'name' => $name,
            'config' => ['autoReconnect' => true],
        ]);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no devolvió sesión al crear.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(string $sessionId): array
    {
        $response = $this->request('get', '/api/sessions/'.$sessionId);

        if (! is_array($response)) {
            throw new RuntimeException('Sesión OpenWA no encontrada: '.$sessionId);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function startSession(string $sessionId): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/start');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no pudo iniciar la sesión.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQrCode(string $sessionId): array
    {
        $response = $this->request('get', '/api/sessions/'.$sessionId.'/qr');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no devolvió código QR.');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function stopSession(string $sessionId): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/stop');

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó la desconexión.');
        }

        return $response;
    }

    /**
     * @return array{attempted: bool, remote: ?array<string, mixed>, error: ?string}
     */
    public function tryStartIfDown(string $sessionId, string $status): array
    {
        if (! in_array($status, ['created', 'disconnected', 'failed'], true)) {
            return ['attempted' => false, 'remote' => null, 'error' => null];
        }

        try {
            $this->startSession($sessionId);
            $remote = $this->waitForSessionProgress($sessionId, $status);

            return ['attempted' => true, 'remote' => $remote, 'error' => null];
        } catch (\Throwable $e) {
            return ['attempted' => true, 'remote' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForSessionProgress(string $sessionId, string $fromStatus): array
    {
        $remote = $this->getSession($sessionId);
        $status = (string) ($remote['status'] ?? $fromStatus);

        for ($i = 0; $i < 4; $i++) {
            if (in_array($status, ['ready', 'qr_ready', 'authenticating'], true)) {
                break;
            }

            $wait = max(0, (int) config('openwa.reconnect_poll_seconds', 3));
            if ($wait > 0) {
                sleep($wait);
            }

            $remote = $this->getSession($sessionId);
            $status = (string) ($remote['status'] ?? $status);
        }

        return $remote;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $sessionId, string $chatId, string $text): array
    {
        $response = $this->request('post', '/api/sessions/'.$sessionId.'/messages/send-text', [
            'chatId' => $chatId,
            'text' => $text,
        ]);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó el envío del mensaje.');
        }

        return $response;
    }

    /**
     * Timeout sin bytes = no se asume envío. 5xx tardío = se asume entregado.
     *
     * @return array<string, mixed>
     */
    public function sendTextWithDeliveryFallback(string $sessionId, string $chatId, string $text): array
    {
        try {
            return $this->sendText($sessionId, $chatId, $text);
        } catch (\Throwable $error) {
            if ($this->isNoResponseTimeout($error)) {
                Log::warning('OpenWA send-text: timeout sin respuesta; no se asume envío', [
                    'error' => $error->getMessage(),
                    'chat_id' => $chatId,
                    'session_id' => $sessionId,
                ]);

                throw $error;
            }

            if ($this->isAmbiguousDeliveryError($error)) {
                Log::warning('OpenWA send-text: respuesta ambigua; se asume envío OK', [
                    'error' => $error->getMessage(),
                    'chat_id' => $chatId,
                ]);

                return ['messageId' => null, 'assumed_delivery' => true];
            }

            throw $error;
        }
    }

    public function isNoResponseTimeout(\Throwable $error): bool
    {
        $message = $error->getMessage();

        return str_contains($message, '0 bytes received')
            || (str_contains($message, 'cURL error 28')
                && (str_contains($message, '0 bytes') || str_contains($message, 'Operation timed out')));
    }

    public function isAmbiguousDeliveryError(\Throwable $error): bool
    {
        $message = $error->getMessage();
        if ($message === '' || $this->isNoResponseTimeout($error)) {
            return false;
        }

        if (str_contains($message, 'Error de red con OpenWA')
            || str_contains($message, 'Internal server error')
            || str_contains($message, '"statusCode":500')) {
            return true;
        }

        return (bool) preg_match('/OpenWA HTTP 5\d{2}/', $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function registerWebhook(string $sessionId, string $url, ?string $secret = null): array
    {
        $payload = [
            'url' => $url,
            'events' => OpenWaWebhookEvents::inboundMessageSubscriptions(),
            'active' => true,
        ];

        if ($secret !== null && $secret !== '') {
            $payload['secret'] = $secret;
            $payload['headers'] = [
                'X-Webhook-Secret' => $secret,
            ];
        }

        $response = $this->request('post', '/api/sessions/'.$sessionId.'/webhooks', $payload);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó el registro del webhook.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateWebhook(string $sessionId, string $webhookId, array $payload): array
    {
        $response = $this->request('put', '/api/sessions/'.$sessionId.'/webhooks/'.$webhookId, $payload);

        if (! is_array($response)) {
            throw new RuntimeException('OpenWA no confirmó la actualización del webhook.');
        }

        return $response;
    }

    public function deleteWebhook(string $sessionId, string $webhookId): void
    {
        $this->request('delete', '/api/sessions/'.$sessionId.'/webhooks/'.$webhookId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(string $sessionId): array
    {
        return $this->unwrapWebhookList(
            $this->request('get', '/api/sessions/'.$sessionId.'/webhooks'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAllWebhooks(int $limit = 1000): array
    {
        return $this->unwrapWebhookList(
            $this->request('get', '/api/webhooks?limit='.$limit),
        );
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $response
     * @return list<array<string, mixed>>
     */
    private function unwrapWebhookList(mixed $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            $response = $response['data'];
        }

        $out = [];
        foreach ($response as $hook) {
            if (is_array($hook)) {
                $out[] = $hook;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function request(string $method, string $path, ?array $body = null): mixed
    {
        $apiKey = trim((string) config('openwa.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENWA_API_KEY no configurada.');
        }

        $url = rtrim((string) config('openwa.api_url'), '/').$path;

        try {
            $pending = Http::timeout((int) config('openwa.timeout_seconds', 45))
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey]);

            $response = match ($method) {
                'get' => $pending->get($url),
                'post' => $pending->post($url, $body ?? []),
                'put' => $pending->put($url, $body ?? []),
                'delete' => $pending->delete($url),
                default => throw new RuntimeException('Método HTTP no soportado: '.$method),
            };
        } catch (RequestException $e) {
            throw new RuntimeException('Error de red con OpenWA: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenWA HTTP '.$response->status().': '.$response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
