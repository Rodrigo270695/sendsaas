<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\SedesXlsxExport;
use App\Http\Requests\SedeRequest;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Sede;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SedeController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'codigo',
        'nombre',
        'distrito',
        'provincia',
        'departamento',
        'telefono',
        'activa',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todas', 'activa', 'inactiva'];

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
            ->with('distritoModel.provincia.departamento');

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $sedes = $query->paginate($perPage)->withQueryString();

        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('configuracion/sedes/index', [
            'sedes' => $sedes,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Sede::query()->where('tenant_id', $tenantId)->count(),
                'activas' => Sede::query()->where('tenant_id', $tenantId)->where('activa', true)->count(),
                'inactivas' => Sede::query()->where('tenant_id', $tenantId)->where('activa', false)->count(),
                'coincidencias' => $sedes->total(),
            ],
            'departamentos' => $departamentos,
        ]);
    }

    public function store(SedeRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort();
        $data = $this->hydrateLocationFromDistrito($request->validated());

        Sede::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
            'codigo' => Sede::generateNextCode($tenantId),
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Sede creada correctamente.');
    }

    public function update(SedeRequest $request, Sede $sede): RedirectResponse
    {
        $this->assertSedeBelongsToTenant($sede);
        $data = $this->hydrateLocationFromDistrito($request->validated());

        $sede->update([
            ...$data,
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Sede actualizada correctamente.');
    }

    public function destroy(Sede $sede): RedirectResponse
    {
        $this->assertSedeBelongsToTenant($sede);
        $sede->delete();

        return back()->with('success', 'Sede eliminada correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $count = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $data['ids'])
            ->delete();

        $message = $count === 1
            ? '1 sede eliminada correctamente.'
            : "{$count} sedes eliminadas correctamente.";

        return back()->with('success', $message);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $tenantId = $this->tenantIdOrAbort();

        $search = trim((string) $request->string('search', ''));
        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($tenantId, $search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'sedes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new SedesXlsxExport;

        return XlsxDownload::from(
            fn (mixed $output) => $exporter->streamTo($query, $output),
            $filename,
        );
    }

    /**
     * @return Builder<Sede>
     */
    private function buildBaseQuery(string $tenantId, string $search, string $estado): Builder
    {
        $query = Sede::query()->where('tenant_id', $tenantId);

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(nombre) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(codigo) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(direccion, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(distrito, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term]);
            });
        }

        if ($estado === 'activa') {
            $query->where('activa', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activa', false);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateLocationFromDistrito(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }

    private function tenantIdOrAbort(): string
    {
        $id = tenant_id();
        abort_if($id === null || $id === '', 403, 'Solo usuarios de una empresa pueden gestionar sedes.');

        return $id;
    }

    private function assertSedeBelongsToTenant(Sede $sede): void
    {
        abort_unless($sede->tenant_id === $this->tenantIdOrAbort(), 404);
    }
}
