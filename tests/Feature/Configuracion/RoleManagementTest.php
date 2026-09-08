<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
});

test('superadmin can view the roles index', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('configuracion.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/roles/index')
            ->has('roles.data')
            ->has('stats')
            ->has('permissions_catalog')
        );
});

test('user without permission cannot view roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('configuracion.roles.index'))
        ->assertForbidden();
});

test('system roles cannot be deleted', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
    $role = Role::query()->where('name', 'superadmin')->whereNull('tenant_id')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('configuracion.roles.index'))
        ->delete(route('configuracion.roles.destroy', $role))
        ->assertRedirect(route('configuracion.roles.index'))
        ->assertSessionHasErrors('name');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('superadmin can export roles as xlsx', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $response = $this->actingAs($admin)
        ->get(route('configuracion.roles.export'))
        ->assertOk()
        ->assertDownload();

    $path = $response->getFile()->getPathname();
    expect(is_file($path))->toBeTrue()
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});

test('superadmin can create a custom role', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->from(route('configuracion.roles.index'))
        ->post(route('configuracion.roles.store'), [
            'name' => 'auditor',
            'description' => 'Revisa reportes de plataforma',
        ])
        ->assertRedirect(route('configuracion.roles.index'))
        ->assertSessionHas('success');

    expect(Role::query()->where('name', 'auditor')->whereNull('tenant_id')->exists())->toBeTrue();
});

test('demo tenant cannot manage role permissions', function () {
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    $demo = User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
    $role = Role::query()
        ->where('name', 'admin_empresa')
        ->where('tenant_id', $demo->tenant_id)
        ->firstOrFail();

    $this->actingAs($demo)
        ->get('http://demo.sendsaas.test/configuracion/roles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mutations_locked', true)
        );

    $this->actingAs($demo)
        ->put('http://demo.sendsaas.test/configuracion/roles/'.$role->id.'/permissions', [
            'permissions' => ['usuarios.view'],
        ])
        ->assertForbidden();

    $this->actingAs($demo)
        ->post('http://demo.sendsaas.test/configuracion/roles', [
            'name' => 'auditor-demo',
            'description' => 'No debe crearse',
        ])
        ->assertForbidden();
});

test('another tenant can still manage role permissions', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'estado' => 'active',
    ]);
    app(TenantRolesSeeder::class)->seedForTenant((string) $tenant->id, true);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'admin@acme.test',
    ]);
    $previous = getPermissionsTeamId();
    setPermissionsTeamId($tenant->id);

    try {
        $admin->syncRoles(['admin_empresa']);
    } finally {
        setPermissionsTeamId($previous);
    }

    $role = Role::query()
        ->where('name', 'agente')
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->get('http://acme.sendsaas.test/configuracion/roles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mutations_locked', false)
        );

    $this->actingAs($admin)
        ->from('http://acme.sendsaas.test/configuracion/roles')
        ->put('http://acme.sendsaas.test/configuracion/roles/'.$role->id.'/permissions', [
            'permissions' => ['usuarios.view'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});
