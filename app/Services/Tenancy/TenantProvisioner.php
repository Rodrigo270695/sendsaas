<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantSchemaMigrator;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\NullOutput;

class TenantProvisioner
{
    /**
     * @param  array{
     *     slug: string,
     *     razon_social: string,
     *     nombre_comercial?: string|null,
     *     email_admin: string,
     *     telefono?: string|null,
     *     plan_id?: string|null,
     *     estado?: string,
     *     trial_ends_at?: \DateTimeInterface|null,
     *     timezone?: string,
     *     locale?: string
     * }  $data
     */
    public function provision(array $data, string $adminPassword, string $adminName = 'Administrador'): Tenant
    {
        $slug = strtolower(trim($data['slug']));
        $schema = TenantManager::schemaFromSlug($slug);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'schema_name' => $schema,
                'plan_id' => $data['plan_id'] ?? null,
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'email_admin' => $data['email_admin'],
                'telefono' => $data['telefono'] ?? null,
                'estado' => $data['estado'] ?? 'trial',
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Lima',
                'locale' => $data['locale'] ?? 'es_PE',
            ],
        );

        $this->ensureSchema($schema);
        $this->ensureAdmin($tenant, $adminName, $data['email_admin'], $adminPassword);

        app(TenantRolesSeeder::class)->seedForTenant((string) $tenant->id, true);

        $admin = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $data['email_admin'])
            ->first();

        if ($admin !== null) {
            $previous = getPermissionsTeamId();
            setPermissionsTeamId($tenant->id);

            try {
                $admin->syncRoles(['admin_empresa']);
            } finally {
                setPermissionsTeamId($previous);
            }
        }

        return $tenant->fresh(['plan']) ?? $tenant;
    }

    private function ensureSchema(string $schema): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! preg_match('/^[a-z0-9_]+$/', $schema)) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$schema.'"');

        $tenantMigrations = glob(database_path('migrations/tenant/*.php')) ?: [];
        if ($tenantMigrations === []) {
            return;
        }

        app(TenantSchemaMigrator::class)->migrate($schema, new NullOutput);
    }

    private function ensureAdmin(Tenant $tenant, string $name, string $email, string $password): void
    {
        $user = User::query()
            ->withTrashed()
            ->where('email', $email)
            ->first();

        $payload = [
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => false,
            'deleted_at' => null,
        ];

        if ($user === null) {
            User::query()->create([
                ...$payload,
                'password' => $password,
            ]);

            return;
        }

        $user->forceFill($payload)->save();

        if ($password !== '') {
            $user->forceFill(['password' => $password])->save();
        }
    }
}
