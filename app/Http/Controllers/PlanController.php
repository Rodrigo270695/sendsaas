<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\PlansXlsxExport;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'codigo',
        'nombre',
        'precio_mensual',
        'orden',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'activos', 'inactivos', 'publicos', 'privados'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $query = $this->buildBaseQuery($search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderBy('orden');
        } else {
            $query->orderBy('orden')->orderBy('precio_mensual');
        }

        $plans = $query
            ->withCount('features')
            ->with([
                'features:plan_id,feature,valor_int,valor_bool,valor_str',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $featureCatalog = collect(Plan::FEATURE_CATALOG)->map(
            fn (array $meta, string $feature) => [
                'feature' => $feature,
                'type' => $meta['type'],
                'group' => $meta['group'],
                'default' => $meta['default'],
            ],
        )->values();

        return Inertia::render('plataforma/planes/index', [
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Plan::query()->count(),
                'activos' => Plan::query()->where('activo', true)->count(),
                'inactivos' => Plan::query()->where('activo', false)->count(),
                'publicos' => Plan::query()->where('es_publico', true)->count(),
                'coincidencias' => $plans->total(),
            ],
            'feature_catalog' => $featureCatalog,
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::query()->create($request->validated());

        return back()->with('success', 'Plan creado correctamente.');
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();

        if ($plan->codigo !== $data['codigo']) {
            throw ValidationException::withMessages([
                'codigo' => 'No puedes cambiar el código de un plan ya creado. Crea un plan nuevo y archiva este.',
            ]);
        }

        $plan->update($data);

        return back()->with('success', 'Plan actualizado correctamente.');
    }

    public function updateFeatures(Request $request, Plan $plan): RedirectResponse
    {
        $allowed = array_keys(Plan::FEATURE_CATALOG);

        $data = $request->validate([
            'features' => ['present', 'array'],
            'features.*.feature' => ['required', 'string', Rule::in($allowed)],
            'features.*.valor_int' => ['nullable', 'integer', 'min:-1', 'max:10000000'],
            'features.*.valor_bool' => ['nullable', 'boolean'],
            'features.*.valor_str' => ['nullable', 'string', 'max:50'],
        ]);

        $catalog = Plan::FEATURE_CATALOG;
        $toUpsert = [];

        foreach ($data['features'] as $entry) {
            $feature = $entry['feature'];
            $type = $catalog[$feature]['type'] ?? null;

            $valorInt = null;
            $valorBool = null;
            $valorStr = null;

            switch ($type) {
                case 'int':
                    $valorInt = isset($entry['valor_int']) ? (int) $entry['valor_int'] : null;
                    break;
                case 'bool':
                    if (array_key_exists('valor_bool', $entry) && $entry['valor_bool'] !== null) {
                        $valorBool = (bool) $entry['valor_bool'];
                    }
                    break;
                case 'str':
                    $valorStr = isset($entry['valor_str']) ? trim((string) $entry['valor_str']) : null;
                    if ($valorStr === '') {
                        $valorStr = null;
                    }
                    break;
            }

            if ($valorInt === null && $valorBool === null && $valorStr === null) {
                continue;
            }

            $toUpsert[$feature] = [
                'valor_int' => $valorInt,
                'valor_bool' => $valorBool,
                'valor_str' => $valorStr,
            ];
        }

        PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->whereNotIn('feature', array_keys($toUpsert))
            ->delete();

        foreach ($toUpsert as $feature => $values) {
            PlanFeature::query()->updateOrCreate(
                ['plan_id' => $plan->id, 'feature' => $feature],
                $values,
            );
        }

        $count = count($toUpsert);
        $message = $count === 0
            ? 'Se removieron todas las features del plan.'
            : ($count === 1
                ? '1 feature configurada en el plan.'
                : "{$count} features configuradas en el plan.");

        return back()->with('success', $message);
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('success', 'Plan eliminado correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $count = Plan::query()->whereIn('id', $data['ids'])->delete();

        $message = $count === 1
            ? '1 plan eliminado correctamente.'
            : "{$count} planes eliminados correctamente.";

        return back()->with('success', $message);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->string('search', ''));

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderBy('orden');
        } else {
            $query->orderBy('orden');
        }

        $filename = 'planes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new PlansXlsxExport;

        return XlsxDownload::from(
            fn (string $path) => $exporter->streamTo($query, $path),
            $filename,
        );
    }

    /**
     * @return Builder<Plan>
     */
    private function buildBaseQuery(string $search, string $estado): Builder
    {
        $query = Plan::query();

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(codigo) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(badge, \'\')) LIKE ?', [$term]);
            });
        }

        switch ($estado) {
            case 'activos':
                $query->where('activo', true);
                break;
            case 'inactivos':
                $query->where('activo', false);
                break;
            case 'publicos':
                $query->where('es_publico', true);
                break;
            case 'privados':
                $query->where('es_publico', false);
                break;
        }

        return $query;
    }
}
