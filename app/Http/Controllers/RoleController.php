<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\RolesXlsxExport;
use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Tenancy\AdminScope;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RoleController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'name',
        'description',
        'permissions_count',
        'created_at',
    ];

    private const TIPO_OPTIONS = ['todos', 'sistema', 'personalizado'];

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

        $tipo = (string) $request->string('tipo', 'todos');
        if (! in_array($tipo, self::TIPO_OPTIONS, true)) {
            $tipo = 'todos';
        }

        $query = $this->buildBaseQuery($search, $tipo);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('name');
        }

        $roles = $query
            ->withCount('permissions')
            ->with(['permissions:id,name'])
            ->paginate($perPage)
            ->withQueryString();

        $statsBase = AdminScope::rolesQuery();

        return Inertia::render('configuracion/roles/index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'tipo' => $tipo,
            ],
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'sistema' => (clone $statsBase)->ofType('sistema')->count(),
                'personalizados' => (clone $statsBase)->ofType('personalizado')->count(),
                'coincidencias' => $roles->total(),
            ],
            'permissions_catalog' => $this->buildPermissionsCatalog(),
            'mutations_locked' => is_public_demo_tenant(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        $data = $request->validated();

        Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
            'tenant_id' => tenant_id(),
        ]);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        AdminScope::assertRoleAccessible($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede renombrar un rol protegido ('.$role->name.').',
            ]);
        }

        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        AdminScope::assertRoleAccessible($role);

        $assignable = AdminScope::assignablePermissionNames();

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists(config('permission.table_names.permissions'), 'name')
                    ->where('guard_name', 'web'),
                Rule::in($assignable),
            ],
        ]);

        $permissions = $data['permissions'] ?? [];

        if ($role->isBaseTenantRole() && $permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => 'No puedes dejar sin permisos un rol base ('.$role->name.').',
            ]);
        }

        if ($role->isPlatformRole() && $permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => 'No puedes dejar sin permisos el rol de plataforma '.$role->name.'.',
            ]);
        }

        $role->syncPermissions($permissions);

        $count = count($permissions);
        $message = $count === 0
            ? 'Se removieron todos los permisos del rol.'
            : ($count === 1
                ? '1 permiso asignado al rol.'
                : "{$count} permisos asignados al rol.");

        return back()->with('success', $message);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        AdminScope::assertRoleAccessible($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar un rol protegido ('.$role->name.').',
            ]);
        }

        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $requestedIds = $data['ids'];

        $deletable = AdminScope::rolesQuery()
            ->whereIn('id', $requestedIds)
            ->whereNotIn('name', Role::protectedRoleNames())
            ->get(['id', 'name']);

        $deletableIds = $deletable->pluck('id')->all();

        if ($deletableIds === []) {
            return back()->with('info', 'No se eliminaron roles: la selección solo incluía roles del sistema.');
        }

        $count = Role::query()->whereIn('id', $deletableIds)->delete();
        $skipped = count($requestedIds) - $count;

        $message = $count === 1
            ? '1 rol eliminado correctamente.'
            : "{$count} roles eliminados correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(' (%d rol%s del sistema se omitieron)', $skipped, $skipped === 1 ? '' : 'es');
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->string('search', ''));
        $tipo = (string) $request->string('tipo', 'todos');
        if (! in_array($tipo, self::TIPO_OPTIONS, true)) {
            $tipo = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $tipo)
            ->withCount('permissions')
            ->with(['permissions:id,name']);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('name');
        }

        $filename = 'roles-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new RolesXlsxExport;

        return XlsxDownload::from(
            fn (string $path) => $exporter->streamTo($query, $path),
            $filename,
        );
    }

    /**
     * En el tenant público `demo` no se crean, editan ni se cambian
     * permisos de roles. El resto de empresas sí.
     */
    private function abortIfDemoRolesLocked(): void
    {
        if (! is_public_demo_tenant()) {
            return;
        }

        abort(403, 'En la empresa demo no se pueden modificar roles ni permisos.');
    }

    /**
     * @return Builder<Role>
     */
    private function buildBaseQuery(string $search, string $tipo): Builder
    {
        $query = AdminScope::rolesQuery();

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$term]);
            });
        }

        $query->ofType($tipo);

        return $query;
    }

    /**
     * @return list<array{module: string, permissions: list<array{id: int, name: string, action: string}>}>
     */
    private function buildPermissionsCatalog(): array
    {
        $allowed = AdminScope::assignablePermissionNames();

        $all = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $allowed)
            ->orderBy('name')
            ->get(['id', 'name']);

        return AdminScope::groupPermissionsCatalog($all);
    }
}
