<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles operativos de cada empresa (tenant). Nunca se mezclan con superadmin.
 *
 *   - admin_empresa  → dueño / admin del tenant
 *   - supervisor     → ve el equipo, asigna, reportes
 *   - agente         → atiende conversaciones asignadas
 */
class TenantRolesSeeder extends Seeder
{
    /**
     * @var array<string, array{description: string, permissions: list<string>}>
     */
    public const ROLES = [
        'admin_empresa' => [
            'description' => 'Administrador de la empresa. Acceso operativo total dentro del tenant, sin permisos de plataforma.',
            'permissions' => [
                'dashboard.view',
                'settings.view',
                'settings.update',
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',
                'conversations.view',
                'conversations.assign',
                'conversations.reply',
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'whatsapp.view',
                'whatsapp.connect',
                'campaigns.view',
                'campaigns.create',
                'automations.view',
                'automations.manage',
                'reports.view',
            ],
        ],
        'supervisor' => [
            'description' => 'Supervisor del equipo de atención. Ve conversaciones, asigna y consulta reportes.',
            'permissions' => [
                'dashboard.view',
                'conversations.view',
                'conversations.assign',
                'conversations.reply',
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'reports.view',
            ],
        ],
        'agente' => [
            'description' => 'Agente de conversaciones. Atiende chats asignados y consulta contactos.',
            'permissions' => [
                'dashboard.view',
                'conversations.view',
                'conversations.reply',
                'contacts.view',
            ],
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('tenants')) {
            $this->command?->warn('TenantRolesSeeder: no existe la tabla tenants.');

            return;
        }

        $tenantIds = Tenant::query()->pluck('id')->all();

        if ($tenantIds === []) {
            $this->command?->warn('TenantRolesSeeder: no hay tenants. Los roles se crean al provisionar cada empresa.');

            return;
        }

        foreach ($tenantIds as $tenantId) {
            $this->seedForTenant((string) $tenantId);
        }
    }

    public function seedForTenant(string $tenantId, bool $forceSync = false): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            $validPermissionNames = Permission::query()
                ->where('guard_name', $guard)
                ->pluck('name')
                ->all();
            $validPermissionSet = array_flip($validPermissionNames);

            foreach (self::ROLES as $name => $definition) {
                $role = Role::query()->firstOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => $guard,
                        'tenant_id' => $tenantId,
                    ],
                    ['description' => $definition['description']],
                );

                if ($role->description !== $definition['description']) {
                    $role->description = $definition['description'];
                    $role->save();
                }

                $perms = array_values(array_filter(
                    $definition['permissions'],
                    fn (string $perm) => isset($validPermissionSet[$perm]),
                ));

                if ($forceSync) {
                    $role->syncPermissions($perms);
                } else {
                    $existing = $role->permissions->pluck('name')->all();
                    $toAdd = array_values(array_diff($perms, $existing));

                    if ($toAdd !== []) {
                        $role->givePermissionTo($toAdd);
                    }
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
