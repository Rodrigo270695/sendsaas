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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
            ->where('openwa.configured', false)
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

function enableOpenWaForTests(): void
{
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
        'openwa.reconnect_poll_seconds' => 0,
    ]);
}

/**
 * @param  array<string, mixed>  $session
 */
function fakeOpenWaSession(array $session, ?string $qrCode = 'data:image/png;base64,xx'): void
{
    $id = (string) $session['id'];

    Http::fake(function (Request $request) use ($session, $id, $qrCode) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'GET' && str_ends_with($url, '/api/sessions')) {
            return Http::response([]);
        }

        if ($method === 'POST' && str_ends_with($url, '/api/sessions')) {
            return Http::response($session, 201);
        }

        if ($method === 'POST' && str_ends_with($url, '/api/sessions/'.$id.'/start')) {
            return Http::response([...$session, 'status' => 'qr_ready']);
        }

        if ($method === 'POST' && str_ends_with($url, '/api/sessions/'.$id.'/stop')) {
            return Http::response(['message' => 'Session stopped']);
        }

        if ($method === 'GET' && str_ends_with($url, '/api/sessions/'.$id.'/qr')) {
            return Http::response([
                'qrCode' => $qrCode,
                'status' => $session['status'] ?? 'qr_ready',
            ]);
        }

        if ($method === 'GET' && str_ends_with($url, '/api/sessions/'.$id)) {
            return Http::response($session);
        }

        return Http::response(['error' => 'unexpected '.$method.' '.$url], 404);
    });
}

test('connect without openwa configured returns 503', function () {
    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/connect')
        ->assertStatus(503);
});

test('qr without openwa configured returns json 503', function () {
    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->getJson('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/qr')
        ->assertStatus(503)
        ->assertJson([
            'ready' => false,
            'qr_code' => null,
        ]);
});

test('connect creates the remote openwa session and stores its id', function () {
    enableOpenWaForTests();
    fakeOpenWaSession([
        'id' => 'ow-1',
        'name' => 'demo',
        'status' => 'qr_ready',
    ]);

    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/connect')
        ->assertRedirect()
        ->assertSessionHas('info');

    $session->refresh();

    expect($session->openwa_session_id)->toBe('ow-1')
        ->and($session->status)->toBe('qr_ready');
});

test('qr endpoint returns the openwa qr code', function () {
    enableOpenWaForTests();
    fakeOpenWaSession([
        'id' => 'ow-1',
        'name' => 'demo',
        'status' => 'qr_ready',
    ], 'data:image/png;base64,qrdemo');

    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'openwa_session_id' => 'ow-1',
        'status' => 'qr_ready',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->getJson('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/qr')
        ->assertOk()
        ->assertJson([
            'ready' => false,
            'qr_code' => 'data:image/png;base64,qrdemo',
            'status' => 'qr_ready',
        ]);
});

test('qr endpoint reports ready and stores the phone', function () {
    enableOpenWaForTests();
    fakeOpenWaSession([
        'id' => 'ow-1',
        'name' => 'demo',
        'status' => 'ready',
        'phone' => '51999111222',
        'pushName' => 'Demo',
    ]);

    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'openwa_session_id' => 'ow-1',
        'status' => 'qr_ready',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->getJson('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/qr')
        ->assertOk()
        ->assertJson([
            'ready' => true,
            'phone' => '51999111222',
            'status' => 'ready',
            'qr_code' => null,
        ]);

    expect($session->fresh()->phone)->toBe('51999111222')
        ->and($session->fresh()->status)->toBe('ready');
});

test('disconnect stops the remote session', function () {
    enableOpenWaForTests();
    fakeOpenWaSession([
        'id' => 'ow-1',
        'name' => 'demo',
        'status' => 'disconnected',
    ]);

    $admin = sesionesAdmin();
    $session = TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_name' => 'demo',
        'openwa_session_id' => 'ow-1',
        'alias' => 'Principal',
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/comunicaciones/sesiones')
        ->post('http://demo.sendsaas.test/comunicaciones/sesiones/'.$session->id.'/disconnect')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($session->fresh()->status)->toBe('disconnected')
        ->and($session->fresh()->phone)->toBeNull();
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
