<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Alcance plataforma vs tenant para roles y usuarios.
 * En el host central (`tenant_id()` null) solo se ven registros sin tenant.
 */
final class AdminScope
{
    public static function isTenantContext(): bool
    {
        return tenant_id() !== null;
    }

    /**
     * @return Builder<Role>
     */
    public static function rolesQuery(): Builder
    {
        $query = Role::query()->where('guard_name', 'web');

        return self::isTenantContext()
            ? $query->where('tenant_id', tenant_id())
            : $query->whereNull('tenant_id');
    }

    /**
     * @return Builder<User>
     */
    public static function usersQuery(): Builder
    {
        $query = User::query();

        return self::isTenantContext()
            ? $query->where('tenant_id', tenant_id())
            : $query->whereNull('tenant_id');
    }

    public static function assertRoleAccessible(Role $role): void
    {
        abort_unless(self::roleIsInScope($role), 404);
    }

    public static function assertUserAccessible(User $user): void
    {
        abort_unless(self::userIsInScope($user), 404);
    }

    public static function roleIsInScope(Role $role): bool
    {
        if (self::isTenantContext()) {
            return $role->tenant_id === tenant_id();
        }

        return $role->tenant_id === null;
    }

    public static function userIsInScope(User $user): bool
    {
        if (self::isTenantContext()) {
            return $user->tenant_id === tenant_id();
        }

        return $user->tenant_id === null;
    }

    /**
     * @return list<string>
     */
    public static function assignablePermissionNames(): array
    {
        $names = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        if (! self::isTenantContext()) {
            return $names;
        }

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => ! str_starts_with($name, 'plataforma-'),
        ));
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return list<array{module: string, permissions: list<array{id: int, name: string, action: string}>}>
     */
    public static function groupPermissionsCatalog(Collection $permissions): array
    {
        return $permissions
            ->groupBy(function (Permission $permission): string {
                $parts = explode('.', $permission->name, 2);

                return $parts[0] !== '' ? $parts[0] : $permission->name;
            })
            ->map(function (Collection $group, string $module): array {
                return [
                    'module' => $module,
                    'permissions' => $group->map(function (Permission $permission): array {
                        $parts = explode('.', $permission->name, 2);

                        return [
                            'id' => (int) $permission->id,
                            'name' => $permission->name,
                            'action' => $parts[1] ?? $permission->name,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
