<?php

use App\Models\Plan;
use App\Models\Sede;
use App\Models\TenantWhatsappSession;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\DemoTenantsSeeder;
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
    $this->seed(DemoTenantsSeeder::class);
});

function sesionesAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

test('tenant admin can view whatsapp sessions index with plan limits', function () {
    $this->actingAs(sesionesAdmin())
        ->get('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('comunicaciones/sesiones/index')
            ->has('sessions.data')
            ->has('stats')
            ->has('sedes')
            ->where('plan_limits.max_whatsapp_sessions.limit', 1)
            ->where('plan_limits.max_whatsapp_sessions.used', 0)
            ->where('plan_limits.max_whatsapp_sessions.reached', false)
        );
});

test('user without permission cannot view sessions', function () {
    $user = User::factory()->create(['tenant_id' => sesionesAdmin()->tenant_id]);

    $this->actingAs($user)
        ->get('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->assertForbidden();
});

test('tenant admin can create the first session on a one-slot plan', function () {
    $admin = sesionesAdmin();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'WhatsApp principal',
            'sede_id' => null,
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session = TenantWhatsappSession::query()->where('tenant_id', $admin->tenant_id)->first();

    expect($session)->not->toBeNull()
        ->and($session->alias)->toBe('WhatsApp principal')
        ->and($session->openwa_session_name)->toBe('demo')
        ->and($session->status)->toBe('created');

    $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan_limits.max_whatsapp_sessions.used', 1)
            ->where('plan_limits.max_whatsapp_sessions.reached', true)
        );
});

test('creating a second session on a one-slot plan fails with plan_limit', function () {
    $admin = sesionesAdmin();

    TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Segundo número',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('plan_limit');

    expect(TenantWhatsappSession::query()->where('tenant_id', $admin->tenant_id)->count())->toBe(1);
});

test('deleting a session frees the plan slot', function () {
    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->delete('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(TenantWhatsappSession::query()->where('tenant_id', $admin->tenant_id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Nuevo canal',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('business plan allows two sessions and blocks the third', function () {
    $plan = Plan::query()->where('codigo', 'business')->firstOrFail();
    $tenant = app(TenantProvisioner::class)->provision(
        [
            'slug' => 'acme',
            'razon_social' => 'Acme SAC',
            'nombre_comercial' => 'Acme',
            'email_admin' => 'admin@acme.test',
            'plan_id' => $plan->id,
            'estado' => 'active',
        ],
        'password',
        'Admin Acme',
    );

    $admin = User::query()->where('email', 'admin@acme.test')->firstOrFail();

    $this->actingAs($admin)
        ->post('http://acme.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Canal 1',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post('http://acme.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Canal 2',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(TenantWhatsappSession::query()->where('tenant_id', $tenant->id)->count())->toBe(2);

    $this->actingAs($admin)
        ->from('http://acme.sendsaas.test/comunicaciones/sesiones')
        ->post('http://acme.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Canal 3',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('plan_limit');
});

test('a sede cannot be assigned to two sessions', function () {
    $admin = sesionesAdmin();
    $sede = Sede::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'codigo' => 'SEDE-001',
        'nombre' => 'Lima',
    ]);

    TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'sede_id' => $sede->id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $plan = Plan::query()->where('codigo', 'business')->firstOrFail();
    $admin->tenant?->update(['plan_id' => $plan->id]);

    $this->actingAs($admin->fresh())
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Otro canal',
            'sede_id' => $sede->id,
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('sede_id');
});

test('tenant admin can update a session alias', function () {
    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Viejo',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->put('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id, [
            'alias' => 'Canal Lima',
            'auto_reconnect' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($session->fresh()->alias)->toBe('Canal Lima')
        ->and($session->fresh()->auto_reconnect)->toBeFalse();
});

test('tenant admin can create a session linked to a sede', function () {
    $admin = sesionesAdmin();
    $sede = Sede::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'codigo' => 'SEDE-001',
        'nombre' => 'Lima',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'WhatsApp Lima',
            'sede_id' => $sede->id,
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionDoesntHaveErrors();

    expect(TenantWhatsappSession::query()->where('tenant_id', $admin->tenant_id)->first()?->sede_id)
        ->toBe($sede->id);
});

test('support impersonation can create a session for the tenant', function () {
    $admin = sesionesAdmin();
    $superadmin = User::query()->where('email', SuperadminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($superadmin)
        ->withSession([
            'tenant_impersonation' => [
                'tenant_id' => (string) $admin->tenant_id,
                'tenant_label' => 'Demo',
            ],
        ])
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones', [
            'alias' => 'Canal soporte',
            'auto_reconnect' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session = TenantWhatsappSession::query()->where('tenant_id', $admin->tenant_id)->first();

    expect($session)->not->toBeNull()
        ->and($session->created_by_id)->toBe($superadmin->id);
});

test('scheduled sends and history pages render empty states', function () {
    $admin = sesionesAdmin();

    $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/comunicaciones/envios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('comunicaciones/envios/index'));

    $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/comunicaciones/historial')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('comunicaciones/historial/index'));
});
