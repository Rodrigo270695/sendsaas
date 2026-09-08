<?php

use App\Models\Plan;
use App\Models\User;
use App\Support\Plan\PlanLimits;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
});

test('superadmin can view the plans index', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('plataforma.planes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plataforma/planes/index')
            ->has('plans.data')
            ->has('stats')
            ->has('feature_catalog')
            ->where('stats.total', 5)
        );
});

test('user without permission cannot view plans', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('plataforma.planes.index'))
        ->assertForbidden();
});

test('seeder defines openwa and outbound quotas', function () {
    $starter = Plan::findByCodigo('starter');
    $enterprise = Plan::findByCodigo('enterprise');

    expect($starter)->not->toBeNull()
        ->and($starter->resolveFeature('max_usuarios'))->toBe(2)
        ->and($starter->resolveFeature('max_whatsapp_sessions'))->toBe(1)
        ->and($starter->resolveFeature('max_outbound_per_day'))->toBe(500)
        ->and(PlanLimits::intLimit($starter, 'max_outbound_per_day'))->toBe(500)
        ->and(PlanLimits::wouldExceed($starter, 'max_outbound_per_day', 500))->toBeTrue()
        ->and(PlanLimits::wouldExceed($starter, 'max_whatsapp_sessions', 0))->toBeFalse()
        ->and(PlanLimits::intLimit($enterprise, 'max_whatsapp_sessions'))->toBeNull()
        ->and(PlanLimits::stringValue($starter, 'send_window_start'))->toBe('08:00')
        ->and(PlanLimits::stringValue($enterprise, 'soporte_tipo'))->toBe('whatsapp_prioritario');
});

test('superadmin can create a plan', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($admin)
        ->from(route('plataforma.planes.index'))
        ->post(route('plataforma.planes.store'), [
            'codigo' => 'agencia',
            'nombre' => 'Agencia',
            'descripcion' => 'Cupos para agencias',
            'badge' => null,
            'color_hex' => '#AB3C3D',
            'precio_mensual' => 79,
            'precio_anual' => null,
            'trial_days' => 7,
            'orden' => 6,
            'es_publico' => true,
            'activo' => true,
        ])
        ->assertRedirect(route('plataforma.planes.index'))
        ->assertSessionHas('success');

    $creado = Plan::query()->where('codigo', 'agencia')->first();
    expect($creado)->not->toBeNull()
        ->and((float) $creado->precio_mensual)->toBe(79.0)
        ->and((float) $creado->precio_anual)->toBe(790.0);
});

test('plan codigo cannot be changed after create', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
    $plan = Plan::findByCodigo('starter');

    $this->actingAs($admin)
        ->from(route('plataforma.planes.index'))
        ->put(route('plataforma.planes.update', $plan), [
            'codigo' => 'starter_v2',
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
            'badge' => $plan->badge,
            'color_hex' => $plan->color_hex,
            'precio_mensual' => $plan->precio_mensual,
            'precio_anual' => $plan->precio_anual,
            'trial_days' => $plan->trial_days,
            'orden' => $plan->orden,
            'es_publico' => $plan->es_publico,
            'activo' => $plan->activo,
        ])
        ->assertRedirect(route('plataforma.planes.index'))
        ->assertSessionHasErrors('codigo');

    expect($plan->fresh()->codigo)->toBe('starter');
});

test('superadmin can update plan features', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();
    $plan = Plan::findByCodigo('starter');

    $this->actingAs($admin)
        ->from(route('plataforma.planes.index'))
        ->put(route('plataforma.planes.update-features', $plan), [
            'features' => [
                ['feature' => 'max_usuarios', 'valor_int' => 4],
                ['feature' => 'max_whatsapp_sessions', 'valor_int' => 2],
                ['feature' => 'max_outbound_per_day', 'valor_int' => 800],
            ],
        ])
        ->assertRedirect(route('plataforma.planes.index'))
        ->assertSessionHas('success');

    $plan->unsetRelation('features');

    expect($plan->fresh()->resolveFeature('max_usuarios'))->toBe(4)
        ->and($plan->fresh()->resolveFeature('max_whatsapp_sessions'))->toBe(2)
        ->and($plan->fresh()->resolveFeature('max_outbound_per_day'))->toBe(800);
});

test('superadmin can export plans as xlsx', function () {
    $admin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $response = $this->actingAs($admin)
        ->get(route('plataforma.planes.export'))
        ->assertOk()
        ->assertDownload();

    $path = $response->getFile()->getPathname();
    expect(is_file($path))->toBeTrue()
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});
