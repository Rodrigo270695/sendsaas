<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use App\Services\Conversations\InboundMessageIngestor;
use App\Support\OpenWa\OpenWaInboundPayload;
use App\Support\OpenWa\OpenWaWebhookEvents;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/openwa/{slug}
 * Header: X-Webhook-Secret = OPENWA_WEBHOOK_SECRET
 */
final class OpenWaWebhookController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly InboundMessageIngestor $ingestor,
    ) {}

    public function handle(Request $request, string $slug): JsonResponse
    {
        $secret = trim((string) config('openwa.webhook_secret', ''));
        if ($secret === '') {
            Log::error('OpenWA webhook rechazado: OPENWA_WEBHOOK_SECRET no configurado.');

            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        if (! $this->verifySecret($request, $secret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = OpenWaInboundPayload::fromRequest($request->all());

        if (! OpenWaWebhookEvents::isInboundChat($payload->event)) {
            return response()->json(['ok' => true, 'skipped' => 'not_message_event']);
        }

        if ($payload->fromMe) {
            return response()->json(['ok' => true, 'skipped' => 'from_me']);
        }

        if ($payload->isGroup()) {
            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        if ($payload->phone === null) {
            return response()->json(['ok' => true, 'skipped' => 'unresolvable_phone']);
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $session = $this->resolveSession($payload->sessionId, (string) $tenant->id);
        if ($session === false) {
            return response()->json(['ok' => true, 'skipped' => 'tenant_mismatch']);
        }

        try {
            $this->tenants->resolveBySlug($slug);
        } catch (TenantNotFoundException) {
            return response()->json(['error' => 'Tenant not found'], 404);
        } catch (TenantSuspendedException) {
            return response()->json(['error' => 'Tenant suspended'], 403);
        }

        try {
            $result = $this->ingestor->ingest($payload, $session);
        } finally {
            $this->tenants->forget();
        }

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }

    /**
     * @return TenantWhatsappSession|null|false false = sesión de otro tenant
     */
    private function resolveSession(string $sessionId, string $tenantId): TenantWhatsappSession|false|null
    {
        if ($sessionId === '') {
            return TenantWhatsappSession::query()
                ->where('tenant_id', $tenantId)
                ->where('status', TenantWhatsappSession::STATUS_CONNECTED)
                ->first();
        }

        $session = TenantWhatsappSession::query()
            ->where('openwa_session_id', $sessionId)
            ->first();

        if ($session === null) {
            return null;
        }

        if ($session->tenant_id !== $tenantId) {
            return false;
        }

        return $session;
    }

    private function verifySecret(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Webhook-Signature', '');
        $openWaSignature = (string) $request->header('X-OpenWA-Signature', '');
        $legacySecret = (string) $request->header('X-Webhook-Secret', '');
        $signatureToVerify = $signature !== '' ? $signature : $openWaSignature;

        if ($signatureToVerify !== '') {
            $expected = 'sha256='.hash_hmac('sha256', (string) $request->getContent(), $secret);

            return hash_equals($expected, $signatureToVerify);
        }

        return $legacySecret !== '' && hash_equals($secret, $legacySecret);
    }
}
