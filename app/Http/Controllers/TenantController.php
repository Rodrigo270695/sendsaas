<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'slug',
        'razon_social',
        'estado',
        'trial_ends_at',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'trial', 'active', 'suspended', 'cancelled'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $planId = trim((string) $request->string('plan_id', ''));

        $query = $this->buildBaseQuery($search, $estado, $planId);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $tenants = $query
            ->with(['plan:id,codigo,nombre,badge,color_hex'])
            ->paginate($perPage)
            ->withQueryString();

        $statsByEstado = Tenant::withTrashed()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        return Inertia::render('plataforma/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'plan_id' => $planId !== '' ? $planId : null,
            ],
            'stats' => [
                'total' => Tenant::withTrashed()->count(),
                'active' => (int) ($statsByEstado['active'] ?? 0),
                'trial' => (int) ($statsByEstado['trial'] ?? 0),
                'suspended' => (int) ($statsByEstado['suspended'] ?? 0),
                'cancelled' => (int) ($statsByEstado['cancelled'] ?? 0),
                'coincidencias' => $tenants->total(),
            ],
            'plans_catalog' => Plan::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre', 'trial_days', 'precio_mensual', 'color_hex']),
        ]);
    }

    public function store(TenantRequest $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();
        $trialDays = (int) ($data['trial_days'] ?? 0);

        $provisioner->provision(
            [
                'slug' => $data['slug'],
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'email_admin' => $data['email_admin'],
                'telefono' => $data['telefono'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'estado' => $trialDays > 0 ? 'trial' : 'active',
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'timezone' => $data['timezone'],
                'locale' => $data['locale'],
            ],
            (string) $data['admin_password'],
            (string) ($data['nombre_comercial'] ?: $data['razon_social']),
        );

        return back()->with('success', 'Empresa creada correctamente.');
    }

    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();

        if ($tenant->slug !== $data['slug'] && in_array($tenant->estado, ['active', 'suspended'], true)) {
            throw ValidationException::withMessages([
                'slug' => 'No puedes cambiar el slug de una empresa activa o suspendida.',
            ]);
        }

        $tenant->update([
            'slug' => $data['slug'],
            'schema_name' => $tenant->slug === $data['slug']
                ? $tenant->schema_name
                : Tenant::schemaFromSlug($data['slug']),
            'razon_social' => $data['razon_social'],
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'email_admin' => $data['email_admin'],
            'telefono' => $data['telefono'] ?? null,
            'plan_id' => $data['plan_id'] ?? $tenant->plan_id,
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
        ]);

        return back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if (! in_array($tenant->estado, ['active', 'trial'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden suspender empresas en prueba o activas.',
            ]);
        }

        $tenant->update([
            'estado' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Empresa suspendida.');
    }

    public function resume(Tenant $tenant): RedirectResponse
    {
        if ($tenant->estado !== 'suspended') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden reanudar empresas suspendidas.',
            ]);
        }

        $tenant->update([
            'estado' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return back()->with('success', 'Empresa reanudada.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if ($tenant->estado === 'active') {
            throw ValidationException::withMessages([
                'id' => 'No se puede eliminar una empresa activa. Suspéndela primero.',
            ]);
        }

        $tenant->update([
            'estado' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        $tenant->delete();

        return back()->with('success', 'Empresa eliminada.');
    }

    /**
     * @return Builder<Tenant>
     */
    private function buildBaseQuery(string $search, string $estado, string $planId): Builder
    {
        $query = Tenant::withTrashed();

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(slug) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(razon_social) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(nombre_comercial, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(ruc, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email_admin) LIKE ?', [$term]);
            });
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($planId === 'sin_plan') {
            $query->whereNull('plan_id');
        } elseif ($planId !== '') {
            $query->where('plan_id', $planId);
        }

        return $query;
    }
}
