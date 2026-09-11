<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);
});

function subscriptionAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

test('tenant admin can open the subscription page', function () {
    $this->actingAs(subscriptionAdmin())
        ->get('http://demo.sendsaas.test/configuracion/suscripcion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/suscripcion/index')
            ->where('subscription.has_subscription', true)
            ->where('subscription.plan.nombre', 'Starter')
            ->where('subscription.plan.codigo', 'starter')
            ->where('subscription.estado', 'active')
            ->where('subscription.ciclo', 'mensual')
            ->where('subscription.precio_pactado', fn ($value) => (float) $value === 49.0)
            ->where('subscription.send_window.start', '08:00')
            ->where('subscription.send_window.end', '20:00')
            ->where('subscription.soporte_tipo', 'email')
            ->where('subscription.urgency', 'ok')
            ->has('subscription.renewal_url')
            ->has('subscription.days_until_renewal')
        );
});

test('user without settings permission cannot view the subscription page', function () {
    $user = User::factory()->create(['tenant_id' => subscriptionAdmin()->tenant_id]);

    $this->actingAs($user)
        ->get('http://demo.sendsaas.test/configuracion/suscripcion')
        ->assertForbidden();
});

test('trial tenant shows remaining days and amber urgency', function () {
    $admin = subscriptionAdmin();
    Tenant::query()->whereKey($admin->tenant_id)->update([
        'estado' => 'trial',
        'trial_ends_at' => now()->addDays(5)->endOfDay(),
    ]);

    $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/configuracion/suscripcion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/suscripcion/index')
            ->where('subscription.estado', 'trial')
            ->where('subscription.urgency', 'amber')
            ->where('subscription.days_until_renewal', 5)
        );
});

test('superadmin on the central host cannot open a tenant subscription page', function () {
    $superadmin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($superadmin)
        ->get('http://sendsaas.test/configuracion/suscripcion')
        ->assertNotFound();
});
