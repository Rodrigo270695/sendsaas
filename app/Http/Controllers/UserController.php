<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\UsersXlsxExport;
use App\Http\Controllers\Concerns\RespondsToApiPeruConsulta;
use App\Http\Requests\UserDocumentsRequest;
use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\ApiPeruDniService;
use App\Support\Tenancy\AdminScope;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    use RespondsToApiPeruConsulta;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'name',
        'email',
        'last_login_at',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'activos', 'inactivos'];

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

        $rol = trim((string) $request->string('rol', ''));
        $query = $this->buildBaseQuery($search, $estado, $rol);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query
            ->with([
                'roles:id,name',
                'createdBy:id,name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $rolesCatalog = AdminScope::rolesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
            ]);

        return Inertia::render('configuracion/usuarios/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'rol' => $rol !== '' ? $rol : null,
            ],
            'stats' => [
                'total' => AdminScope::usersQuery()->count(),
                'activos' => AdminScope::usersQuery()->where('is_active', true)->count(),
                'inactivos' => AdminScope::usersQuery()->where('is_active', false)->count(),
                'coincidencias' => $users->total(),
            ],
            'roles_catalog' => $rolesCatalog,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'tenant_id' => tenant_id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'],
            'must_change_password' => true,
            'created_by_id' => $request->user()?->id,
        ]);

        $this->withTeam(function () use ($user, $data): void {
            $user->syncRoles([$data['role']]);
        });

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        AdminScope::assertUserAccessible($user);

        if ($request->user()?->id === $user->id && $request->boolean('is_active') === false) {
            throw ValidationException::withMessages([
                'is_active' => 'No puedes suspender tu propia cuenta.',
            ]);
        }

        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
            $payload['must_change_password'] = false;
        }

        $user->update($payload);

        $this->withTeam(function () use ($user, $data): void {
            $user->syncRoles([$data['role']]);
        });

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function updateDocuments(UserDocumentsRequest $request, User $user): RedirectResponse
    {
        AdminScope::assertUserAccessible($user);

        $data = $request->validated();
        $payload = [
            'colegiatura' => $data['colegiatura'] ?? $user->colegiatura,
        ];

        foreach (['cv' => 'cv_path', 'dni_file' => 'dni_file_path', 'firma' => 'firma_path'] as $input => $column) {
            $file = $request->file($input);
            if ($file instanceof UploadedFile) {
                if (filled($user->{$column})) {
                    Storage::disk('public')->delete((string) $user->{$column});
                }
                $payload[$column] = $file->store('users/'.$user->id, 'public');
            }
        }

        $user->update($payload);

        return back()->with('success', 'Documentos actualizados correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        AdminScope::assertUserAccessible($user);

        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'email' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        if ($user->isPlatformSuperadmin()) {
            throw ValidationException::withMessages([
                'email' => 'No se puede eliminar al superadministrador de plataforma.',
            ]);
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $currentId = $request->user()?->id;

        $deletable = AdminScope::usersQuery()
            ->whereIn('id', $data['ids'])
            ->when($currentId, fn (Builder $query) => $query->where('id', '!=', $currentId))
            ->get();

        $blocked = $deletable->filter(fn (User $user): bool => $user->isPlatformSuperadmin());
        $toDelete = $deletable->reject(fn (User $user): bool => $user->isPlatformSuperadmin());

        if ($toDelete->isEmpty()) {
            return back()->with('info', 'No se eliminaron usuarios: la selección estaba protegida.');
        }

        $toDelete->each->delete();

        $message = $toDelete->count() === 1
            ? '1 usuario eliminado correctamente.'
            : $toDelete->count().' usuarios eliminados correctamente.';

        if ($blocked->isNotEmpty()) {
            $message .= ' Se omitió el superadministrador.';
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->string('search', ''));
        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }
        $rol = trim((string) $request->string('rol', ''));

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado, $rol)
            ->with([
                'roles:id,name',
                'createdBy:id,name',
            ]);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'usuarios-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new UsersXlsxExport;

        return XlsxDownload::from(
            fn (string $path) => $exporter->streamTo($query, $path),
            $filename,
        );
    }

    /**
     * @return Builder<User>
     */
    public function consultaDni(Request $request, ApiPeruDniService $apiPeru): JsonResponse
    {
        abort_unless(
            $request->user()?->can('usuarios.create')
            || $request->user()?->can('usuarios.update'),
            403,
        );

        $dni = preg_replace('/\D+/', '', (string) $request->query('dni', ''));
        $request->merge(['dni' => $dni]);

        $validated = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
        ]);

        return $this->consultaApiPeruResponse(
            fn () => $apiPeru->consultar($validated['dni']),
        );
    }

    private function buildBaseQuery(string $search, string $estado, string $rol): Builder
    {
        $query = AdminScope::usersQuery();

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        if ($estado === 'activos') {
            $query->where('is_active', true);
        } elseif ($estado === 'inactivos') {
            $query->where('is_active', false);
        }

        if ($rol !== '') {
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $rol));
        }

        return $query;
    }

    /**
     * @param  callable(): void  $callback
     */
    private function withTeam(callable $callback): void
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId(tenant_id());

        try {
            $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
