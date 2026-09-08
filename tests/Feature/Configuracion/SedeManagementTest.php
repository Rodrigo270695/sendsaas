<?php

use App\Models\Sede;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SeedsGeoCatalog;

uses(RefreshDatabase::class);
uses(SeedsGeoCatalog::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);
});

function demoAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

test('tenant admin can view sedes index', function () {
    $this->actingAs(demoAdmin())
        ->get('http://demo.sendsaas.test/configuracion/sedes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/sedes/index')
            ->has('sedes.data')
            ->has('stats')
            ->has('departamentos')
        );
});

test('user without permission cannot view sedes', function () {
    $user = User::factory()->create(['tenant_id' => demoAdmin()->tenant_id]);

    $this->actingAs($user)
        ->get('http://demo.sendsaas.test/configuracion/sedes')
        ->assertForbidden();
});

test('tenant admin can create a sede with distrito', function () {
    $distrito = $this->seedGeoCatalog();
    $admin = demoAdmin();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/configuracion/sedes')
        ->post('http://demo.sendsaas.test/configuracion/sedes', [
            'nombre' => 'Sede Chiclayo',
            'direccion' => 'Av. Balta 123',
            'telefono' => '999888777',
            'email' => 'chiclayo@demo.test',
            'distrito_id' => $distrito->id,
            'activa' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $sede = Sede::query()->where('tenant_id', $admin->tenant_id)->first();
    expect($sede)->not->toBeNull()
        ->and($sede->codigo)->toBe('SEDE-001')
        ->and($sede->distrito)->toBe('CHICLAYO')
        ->and($sede->provincia)->toBe('CHICLAYO')
        ->and($sede->departamento)->toBe('LAMBAYEQUE');
});

test('creating a sede without distrito fails validation', function () {
    $this->actingAs(demoAdmin())
        ->from('http://demo.sendsaas.test/configuracion/sedes')
        ->post('http://demo.sendsaas.test/configuracion/sedes', [
            'nombre' => 'Sede sin ubigeo',
            'activa' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('distrito_id');
});

test('tenant admin can update and delete a sede', function () {
    $distrito = $this->seedGeoCatalog();
    $admin = demoAdmin();
    $sede = Sede::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'codigo' => 'SEDE-001',
        'nombre' => 'Antigua',
        'distrito_id' => $distrito->id,
        'distrito' => 'CHICLAYO',
        'provincia' => 'CHICLAYO',
        'departamento' => 'LAMBAYEQUE',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/configuracion/sedes')
        ->put('http://demo.sendsaas.test/configuracion/sedes/'.$sede->id, [
            'nombre' => 'Sede actualizada',
            'direccion' => 'Nueva 1',
            'telefono' => '911111111',
            'email' => $sede->email,
            'distrito_id' => $distrito->id,
            'activa' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($sede->fresh()->nombre)->toBe('Sede actualizada')
        ->and($sede->fresh()->activa)->toBeFalse();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/configuracion/sedes')
        ->delete('http://demo.sendsaas.test/configuracion/sedes/'.$sede->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Sede::query()->whereKey($sede->id)->exists())->toBeFalse();
});

test('geo endpoints return cascaded catalog', function () {
    $distrito = $this->seedGeoCatalog();
    $departamentoId = $distrito->provincia->departamento_id;
    $provinciaId = $distrito->provincia_id;

    $this->actingAs(demoAdmin())
        ->getJson('http://demo.sendsaas.test/geo/departamentos')
        ->assertOk()
        ->assertJsonFragment(['name' => 'LAMBAYEQUE']);

    $this->actingAs(demoAdmin())
        ->getJson('http://demo.sendsaas.test/geo/provincias?departamento_id='.$departamentoId)
        ->assertOk()
        ->assertJsonFragment(['name' => 'CHICLAYO']);

    $this->actingAs(demoAdmin())
        ->getJson('http://demo.sendsaas.test/geo/distritos?provincia_id='.$provinciaId)
        ->assertOk()
        ->assertJsonFragment(['name' => 'CHICLAYO']);
});

test('tenant admin can export sedes as xlsx', function () {
    $admin = demoAdmin();
    Sede::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'codigo' => 'SEDE-001',
    ]);

    $response = $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/configuracion/sedes/export')
        ->assertOk()
        ->assertDownload();

    $path = $response->getFile()->getPathname();
    expect(is_file($path))->toBeTrue()
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});
