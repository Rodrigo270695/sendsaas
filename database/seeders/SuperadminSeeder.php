<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Crea (o re-sincroniza) el rol `superadmin` y el usuario de plataforma.
 * Mapeado de VetSaaS: team null, rol con tenant_id null, syncRoles.
 */
class SuperadminSeeder extends Seeder
{
    public const EMAIL = 'superadmin@sendsaas.pe';

    public function run(): void
    {
        $email = (string) env('PLATFORM_SUPERADMIN_EMAIL', self::EMAIL);
        $password = (string) env('PLATFORM_SUPERADMIN_PASSWORD', 'password');
        $displayName = trim((string) env('PLATFORM_SUPERADMIN_NAME', 'Super administrador'));
        if ($displayName === '') {
            $displayName = 'Super administrador';
        }

        if ($email === '' || $password === '') {
            $this->command?->warn('SuperadminSeeder omitido: define PLATFORM_SUPERADMIN_EMAIL y PLATFORM_SUPERADMIN_PASSWORD en .env');

            return;
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $role = Role::query()
                ->whereNull('tenant_id')
                ->where('name', 'superadmin')
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                $role = Role::query()->create([
                    'name' => 'superadmin',
                    'guard_name' => 'web',
                    'tenant_id' => null,
                    'description' => 'Administrador de la plataforma SaaS. No pertenece a ningún tenant.',
                ]);
            }

            $role->forceFill([
                'description' => 'Administrador de la plataforma SaaS. No pertenece a ningún tenant.',
                'tenant_id' => null,
            ])->save();

            $role->syncPermissions(
                Permission::query()->where('guard_name', 'web')->pluck('id')->all()
            );

            $user = User::query()
                ->withTrashed()
                ->whereIn('email', [$email, 'admin@omnidesk.test'])
                ->first();

            if ($user === null) {
                $user = User::query()->create([
                    'tenant_id' => null,
                    'name' => $displayName,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => false,
                ]);
            } else {
                $user->forceFill([
                    'tenant_id' => null,
                    'email' => $email,
                    'name' => $displayName,
                    'is_active' => true,
                    'deleted_at' => null,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }

            $user->syncRoles([$role]);
            Cache::forget('sendsaas.platform_greeting_name');
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        $this->command?->info(sprintf(
            'Superadmin creado: %s (rol: superadmin, %d permisos)',
            $email,
            Permission::query()->where('guard_name', 'web')->count(),
        ));
    }
}
