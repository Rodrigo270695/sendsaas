<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsappSessionRequest;
use App\Models\Sede;
use App\Models\TenantWhatsappSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\WhatsappSessionLinker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappSessionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = [
        'alias',
        'openwa_session_name',
        'phone',
        'status',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todas', 'conectada', 'pendiente', 'desconectada'];

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantIdOrAbort();

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $query = $this->buildBaseQuery($tenantId, $search, $estado)
            ->with('sede:id,nombre,codigo');

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $sessions = $query->paginate($perPage)->withQueryString();

        $base = TenantWhatsappSession::query()->where('tenant_id', $tenantId);

        $sedes = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('comunicaciones/sesiones/index', [
            'sessions' => $sessions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'conectadas' => (clone $base)->where('status', TenantWhatsappSession::STATUS_CONNECTED)->count(),
                'pendientes' => (clone $base)->whereIn('status', TenantWhatsappSession::STATUSES_PENDING)->count(),
                'desconectadas' => (clone $base)->whereIn('status', TenantWhatsappSession::STATUSES_DISCONNECTED)->count(),
                'coincidencias' => $sessions->total(),
            ],
            'sedes' => $sedes,
            'openwa' => [
                'configured' => app(OpenWaClient::class)->isConfigured(),
            ],
        ]);
    }

    public function connect(
        TenantWhatsappSession $whatsappSession,
        WhatsappSessionLinker $linker,
        OpenWaClient $client,
    ): RedirectResponse {
        $this->assertBelongsToTenant($whatsappSession);
        abort_unless($client->isConfigured(), 503, 'OpenWA no está configurado. Pide a soporte que revise OPENWA_API_URL y OPENWA_API_KEY.');

        try {
            $linker->ensureRemote($whatsappSession, wake: true);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo iniciar la sesión de WhatsApp. '.$e->getMessage());
        }

        return back()->with('info', 'Sesión lista para vincular. Escanea el QR con el teléfono.');
    }

    public function qr(
        TenantWhatsappSession $whatsappSession,
        WhatsappSessionLinker $linker,
        OpenWaClient $client,
    ): JsonResponse {
        $this->assertBelongsToTenant($whatsappSession);

        if (! $client->isConfigured()) {
            return response()->json([
                'ready' => false,
                'status' => $whatsappSession->status,
                'qr_code' => null,
                'error' => 'OpenWA no está configurado en el servidor. Pide a soporte que revise OPENWA_API_URL y OPENWA_API_KEY.',
            ], 503);
        }

        try {
            if ($whatsappSession->openwa_session_id === null || $whatsappSession->openwa_session_id === '') {
                $whatsappSession = $linker->ensureRemote($whatsappSession, wake: true);
            }

            if (! $whatsappSession->isReady()) {
                $remote = $client->getSession((string) $whatsappSession->openwa_session_id);
                $status = (string) ($remote['status'] ?? $whatsappSession->status);
                if (in_array($status, ['created', 'disconnected', 'failed'], true)) {
                    $client->tryStartIfDown((string) $whatsappSession->openwa_session_id, $status);
                }
            }

            $whatsappSession = $linker->refresh($whatsappSession);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ready' => false,
                'status' => $whatsappSession->status,
                'qr_code' => null,
                'error' => 'No se pudo sincronizar con OpenWA. Revisa OPENWA_API_KEY.',
            ], 503);
        }

        if ($whatsappSession->isReady()) {
            return response()->json([
                'ready' => true,
                'phone' => $whatsappSession->phone,
                'status' => $whatsappSession->status,
                'qr_code' => null,
            ]);
        }

        try {
            $qr = $client->getQrCode((string) $whatsappSession->openwa_session_id);
            $qrCode = $qr['qrCode'] ?? null;

            return response()->json([
                'ready' => false,
                'status' => (string) ($qr['status'] ?? $whatsappSession->status),
                'qr_code' => is_string($qrCode) && $qrCode !== '' ? $qrCode : null,
                'message' => filled($qrCode) ? null : 'Esperando código QR de WhatsApp…',
            ]);
        } catch (\Throwable $e) {
            report($e);

            $status = (string) $whatsappSession->status;
            $waiting = in_array($status, ['initializing', 'authenticating', 'created'], true);

            return response()->json([
                'ready' => false,
                'status' => $status,
                'qr_code' => null,
                'message' => $waiting ? 'Iniciando sesión… el QR aparece en unos segundos.' : null,
                'error' => $waiting ? null : 'No se pudo obtener el código QR.',
            ], $waiting ? 200 : 503);
        }
    }

    public function disconnect(
        TenantWhatsappSession $whatsappSession,
        WhatsappSessionLinker $linker,
        OpenWaClient $client,
    ): RedirectResponse {
        $this->assertBelongsToTenant($whatsappSession);
        abort_unless($client->isConfigured(), 503, 'OpenWA no está configurado en el servidor.');

        try {
            $linker->disconnect($whatsappSession);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo desvincular WhatsApp. Intenta de nuevo.');
        }

        return back()->with('success', 'WhatsApp desvinculado. Puedes escanear un QR de nuevo cuando quieras.');
    }

    public function store(WhatsappSessionRequest $request): RedirectResponse
    {
        $tenant = current_tenant();
        abort_if($tenant === null, 403, 'Solo usuarios de una empresa pueden gestionar sesiones.');

        $data = $request->validated();

        TenantWhatsappSession::query()->create([
            'tenant_id' => $tenant->id,
            'sede_id' => $data['sede_id'] ?? null,
            'alias' => $data['alias'],
            'openwa_session_name' => TenantWhatsappSession::generateSessionName(
                $tenant->id,
                (string) $tenant->slug,
            ),
            'status' => 'created',
            'auto_reconnect' => $data['auto_reconnect'],
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Sesión de WhatsApp creada. El cupo de tu plan se actualizó.');
    }

    public function update(WhatsappSessionRequest $request, TenantWhatsappSession $whatsappSession): RedirectResponse
    {
        $this->assertBelongsToTenant($whatsappSession);
        $data = $request->validated();

        $whatsappSession->update([
            'alias' => $data['alias'],
            'sede_id' => $data['sede_id'] ?? null,
            'auto_reconnect' => $data['auto_reconnect'],
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Sesión actualizada correctamente.');
    }

    public function destroy(TenantWhatsappSession $whatsappSession): RedirectResponse
    {
        $this->assertBelongsToTenant($whatsappSession);
        $whatsappSession->delete();

        return back()->with('success', 'Sesión eliminada. Quedó un cupo libre en tu plan.');
    }

    /**
     * @return Builder<TenantWhatsappSession>
     */
    private function buildBaseQuery(string $tenantId, string $search, string $estado): Builder
    {
        $query = TenantWhatsappSession::query()->where('tenant_id', $tenantId);

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(alias) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(openwa_session_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(push_name, \'\')) LIKE ?', [$term]);
            });
        }

        if ($estado === 'conectada') {
            $query->where('status', TenantWhatsappSession::STATUS_CONNECTED);
        } elseif ($estado === 'pendiente') {
            $query->whereIn('status', TenantWhatsappSession::STATUSES_PENDING);
        } elseif ($estado === 'desconectada') {
            $query->whereIn('status', TenantWhatsappSession::STATUSES_DISCONNECTED);
        }

        return $query;
    }

    private function tenantIdOrAbort(): string
    {
        $id = tenant_id();
        abort_if($id === null || $id === '', 403, 'Solo usuarios de una empresa pueden gestionar sesiones.');

        return $id;
    }

    private function assertBelongsToTenant(TenantWhatsappSession $session): void
    {
        abort_unless($session->tenant_id === $this->tenantIdOrAbort(), 404);
    }
}
