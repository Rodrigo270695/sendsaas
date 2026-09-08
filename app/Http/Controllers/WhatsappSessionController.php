<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsappSessionRequest;
use App\Models\Sede;
use App\Models\TenantWhatsappSession;
use Illuminate\Database\Eloquent\Builder;
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
        ]);
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
