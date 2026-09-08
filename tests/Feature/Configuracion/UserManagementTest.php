<?php

use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
});

test('superadmin can view the users index', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('configuracion.usuarios.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/usuarios/index')
            ->has('users.data')
            ->has('stats')
            ->has('roles_catalog')
        );
});

test('superadmin can export users as xlsx', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $response = $this->actingAs($admin)
        ->get(route('configuracion.usuarios.export'))
        ->assertOk()
        ->assertDownload();

    $path = $response->getFile()->getPathname();
    expect(is_file($path))->toBeTrue()
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});

test('user without permission cannot view users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('configuracion.usuarios.index'))
        ->assertForbidden();
});

test('a user cannot delete their own account', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->from(route('configuracion.usuarios.index'))
        ->delete(route('configuracion.usuarios.destroy', $admin))
        ->assertRedirect(route('configuracion.usuarios.index'))
        ->assertSessionHasErrors('email');

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('platform superadmin cannot be deleted by another user', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
    $other = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($other)
        ->delete(route('configuracion.usuarios.destroy', $admin))
        ->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('superadmin can lookup dni via apiperu', function () {
    Cache::flush();
    config()->set('services.apiperu.token', 'apiperu-token');
    config()->set('services.apiperu.base_url', 'https://apiperu.dev/api');

    Http::fake([
        'https://apiperu.dev/api/dni' => Http::response([
            'success' => true,
            'data' => [
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => 'DIAZ',
                'nombre_completo' => 'LOPEZ DIAZ MARIA',
            ],
        ], 200),
    ]);

    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->getJson(route('configuracion.usuarios.consulta-dni', ['dni' => '77344506']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'dni' => '77344506',
                'nombres' => 'MARIA',
                'apellidos' => 'LOPEZ DIAZ',
            ],
        ]);
});

test('user without permission cannot lookup dni', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('configuracion.usuarios.consulta-dni', ['dni' => '77344506']))
        ->assertForbidden();
});

test('demo seed user cannot be edited or receive documents', function () {
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    $demo = User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
    $host = 'http://demo.sendsaas.test';

    $this->actingAs($demo)
        ->get($host.'/configuracion/usuarios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.demo_locked', true)
        );

    $this->actingAs($demo)
        ->put($host.'/configuracion/usuarios/'.$demo->id, [
            'name' => 'Hackeado',
            'email' => $demo->email,
            'is_active' => true,
            'role' => 'admin_empresa',
        ])
        ->assertForbidden();

    $this->actingAs($demo)
        ->put($host.'/configuracion/usuarios/'.$demo->id.'/documentos', [
            'colegiatura' => '123',
        ])
        ->assertForbidden();

    expect($demo->fresh()->name)->toBe('Administrador demo');
});

test('superadmin in support mode cannot edit the demo seed user', function () {
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    $demo = User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->withSession([
            'tenant_impersonation' => [
                'tenant_id' => (string) $demo->tenant_id,
                'tenant_label' => 'Demo',
            ],
        ])
        ->put('http://demo.sendsaas.test/configuracion/usuarios/'.$demo->id, [
            'name' => 'Soporte editó',
            'email' => $demo->email,
            'is_active' => true,
            'role' => 'admin_empresa',
        ])
        ->assertForbidden();

    expect($demo->fresh()->name)->toBe('Administrador demo');
});

test('another user in the demo tenant can still be updated', function () {
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    $demo = User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
    $other = User::factory()->create([
        'tenant_id' => $demo->tenant_id,
        'name' => 'Agente demo',
        'email' => 'agente@demo.test',
    ]);

    $previous = getPermissionsTeamId();
    setPermissionsTeamId($demo->tenant_id);

    try {
        $other->syncRoles(['agente']);
    } finally {
        setPermissionsTeamId($previous);
    }

    $this->actingAs($demo)
        ->from('http://demo.sendsaas.test/configuracion/usuarios')
        ->put('http://demo.sendsaas.test/configuracion/usuarios/'.$other->id, [
            'name' => 'Agente actualizado',
            'email' => $other->email,
            'is_active' => true,
            'role' => 'agente',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($other->fresh()->name)->toBe('Agente actualizado');
});
