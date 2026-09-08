<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\DemoTenant;
use Illuminate\Database\Seeder;

class DemoTenantsSeeder extends Seeder
{
    public const SLUG = DemoTenant::SLUG;

    public const EMAIL = DemoTenant::ADMIN_EMAIL;

    public const PASSWORD = 'demo1234';

    public function run(): void
    {
        $planId = Plan::query()->where('codigo', 'starter')->value('id');

        $tenant = app(TenantProvisioner::class)->provision(
            [
                'slug' => self::SLUG,
                'razon_social' => 'SendSaaS Demo',
                'nombre_comercial' => 'Demo',
                'email_admin' => self::EMAIL,
                'plan_id' => $planId,
                'estado' => 'active',
                'trial_ends_at' => null,
                'timezone' => 'America/Lima',
                'locale' => 'es_PE',
            ],
            self::PASSWORD,
            'Administrador demo',
        );

        $root = (string) config('tenant.root_domain', 'sendsaas.orvae.pe');

        $this->command?->info(sprintf(
            'Tenant demo: https://%s.%s  ·  %s / %s',
            $tenant->slug,
            $root,
            self::EMAIL,
            self::PASSWORD,
        ));
    }
}
