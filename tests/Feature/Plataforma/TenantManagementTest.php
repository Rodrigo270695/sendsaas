<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
});

function superadmin(): User
{
    return User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
}

test('superadmin can view the tenants index', function () {
    Tenant::factory()->create([
        'slug' => 'acme',
        'razon_social' => 'Acme SAC',
        'email_admin' => 'admin@acme.test',
        'estado' => 'active',
    ]);

    $this->actingAs(superadmin())
        ->get(route('plataforma.tenants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plataforma/tenants/index')
            ->has('tenants.data', 1)
            ->has('stats')
            ->has('plans_catalog')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
        );
});

test('user without permission cannot view tenants', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('plataforma.tenants.index'))
        ->assertForbidden();
});

test('demo seeder creates the demo tenant and admin', function () {
    $this->seed(DemoTenantsSeeder::class);

    $tenant = Tenant::query()->where('slug', DemoTenantsSeeder::SLUG)->first();
    $user = User::query()->where('email', DemoTenantsSeeder::EMAIL)->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->email_admin)->toBe(DemoTenantsSeeder::EMAIL)
        ->and($tenant->estado)->toBe('active')
        ->and($tenant->schema_name)->toBe('od_demo')
        ->and($user)->not->toBeNull()
        ->and((string) $user->tenant_id)->toBe((string) $tenant->id)
        ->and(Hash::check(DemoTenantsSeeder::PASSWORD, $user->password))->toBeTrue();
});

test('superadmin can create a tenant', function () {
    $plan = Plan::query()->where('codigo', 'starter')->firstOrFail();

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->post(route('plataforma.tenants.store'), [
            'slug' => 'acme-corp',
            'razon_social' => 'Acme Corp SAC',
            'nombre_comercial' => 'Acme',
            'email_admin' => 'admin@acme.test',
            'telefono' => '999111222',
            'plan_id' => $plan->id,
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
            'trial_days' => 14,
            'admin_password' => 'secret123',
        ])
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHas('success');

    $tenant = Tenant::query()->where('slug', 'acme-corp')->first();
    $admin = User::query()->where('email', 'admin@acme.test')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->estado)->toBe('trial')
        ->and((string) $tenant->plan_id)->toBe((string) $plan->id)
        ->and($admin)->not->toBeNull()
        ->and((string) $admin->tenant_id)->toBe((string) $tenant->id)
        ->and(Hash::check('secret123', $admin->password))->toBeTrue();
});

test('superadmin can suspend and resume a tenant', function () {
    $tenant = Tenant::factory()->create(['estado' => 'active']);

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->post(route('plataforma.tenants.suspend', $tenant), [
            'reason' => 'Impago de prueba',
        ])
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHas('success');

    expect($tenant->fresh()->estado)->toBe('suspended');

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->post(route('plataforma.tenants.resume', $tenant))
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHas('success');

    expect($tenant->fresh()->estado)->toBe('active')
        ->and($tenant->fresh()->suspension_reason)->toBeNull();
});

test('cannot delete an active tenant', function () {
    $tenant = Tenant::factory()->create(['estado' => 'active']);

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->delete(route('plataforma.tenants.destroy', $tenant))
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHasErrors('id');

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

test('can delete a suspended tenant', function () {
    $tenant = Tenant::factory()->create(['estado' => 'suspended']);

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->delete(route('plataforma.tenants.destroy', $tenant))
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHas('success');

    expect(Tenant::withTrashed()->find($tenant->id)?->estado)->toBe('cancelled')
        ->and(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse();
});

test('impersonation start redirects to the tenant accept url', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'estado' => 'active',
    ]);

    $response = $this->actingAs(superadmin())
        ->post(route('plataforma.tenants.impersonate', $tenant));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('acme.sendsaas.test/impersonate/accept?token=');
});

test('impersonation accept logs the superadmin into the tenant host', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'estado' => 'active',
        'nombre_comercial' => 'Acme',
    ]);
    $admin = superadmin();
    $token = str_repeat('a', 64);

    Cache::put('tenant_impersonate:'.$token, [
        'superadmin_id' => (string) $admin->id,
        'tenant_id' => (string) $tenant->id,
        'central_origin' => 'http://sendsaas.test',
    ], now()->addMinutes(5));

    $this->get('http://acme.sendsaas.test/impersonate/accept?token='.$token)
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($admin);
    expect(session('tenant_impersonation.tenant_id'))->toBe((string) $tenant->id)
        ->and(session('tenant_impersonation.tenant_label'))->toBe('Acme');
});

test('cannot impersonate a suspended tenant', function () {
    $tenant = Tenant::factory()->create(['estado' => 'suspended']);

    $this->actingAs(superadmin())
        ->from(route('plataforma.tenants.index'))
        ->post(route('plataforma.tenants.impersonate', $tenant))
        ->assertRedirect(route('plataforma.tenants.index'))
        ->assertSessionHasErrors('tenant');
});
