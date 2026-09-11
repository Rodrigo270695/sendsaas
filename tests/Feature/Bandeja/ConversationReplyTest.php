<?php

use App\Models\ConversationMessage;
use App\Models\TenantWhatsappSession;
use App\Models\UsageRecord;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);
});

function replyAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

function enableOpenWaReply(): void
{
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
    ]);
}

test('agent can send a text reply and it counts toward the daily quota', function () {
    enableOpenWaReply();
    Http::fake([
        'wa.test/api/sessions/ow-1/messages/send-text' => Http::response(['messageId' => 'wamid.out-1']),
    ]);

    $admin = replyAdmin();
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_id' => 'ow-1',
        'openwa_session_name' => 'ss-demo',
    ]);
    $conversation = seedInboxThread();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->post('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/mensajes', [
            'body' => 'Claro, te ayudo',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $outbound = ConversationMessage::query()->where('direction', 'out')->first();
    expect($outbound)->not->toBeNull()
        ->and($outbound->body)->toBe('Claro, te ayudo')
        ->and($outbound->sender_id)->toBe($admin->id)
        ->and($outbound->external_id)->toBe('wamid.out-1');

    expect(UsageRecord::query()->where('metric', UsageRecord::METRIC_OUTBOUND_DAY)->value('used'))->toBe(1);

    Http::assertSent(fn ($request) => $request['chatId'] === '51999988877@c.us'
        && $request['text'] === 'Claro, te ayudo');
});

test('user without reply permission cannot send', function () {
    $user = User::factory()->create(['tenant_id' => replyAdmin()->tenant_id]);
    $conversation = seedInboxThread();

    $this->actingAs($user)
        ->post('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/mensajes', [
            'body' => 'Hola',
        ])
        ->assertForbidden();
});

test('reply is blocked when the daily quota is exhausted', function () {
    enableOpenWaReply();
    Http::fake();

    $admin = replyAdmin();
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $admin->tenant_id,
        'openwa_session_id' => 'ow-1',
        'openwa_session_name' => 'ss-demo',
    ]);
    $conversation = seedInboxThread();

    UsageRecord::query()->create([
        'tenant_id' => $admin->tenant_id,
        'metric' => UsageRecord::METRIC_OUTBOUND_DAY,
        'period' => now('America/Lima')->toDateString(),
        'used' => 500,
    ]);

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->post('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/mensajes', [
            'body' => 'Otro más',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ConversationMessage::query()->where('direction', 'out')->count())->toBe(0);
    Http::assertNothingSent();
});

test('reply is blocked when there is no ready whatsapp session', function () {
    enableOpenWaReply();
    Http::fake();
    $conversation = seedInboxThread();

    $this->actingAs(replyAdmin())
        ->from('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->post('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/mensajes', [
            'body' => 'Hola',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ConversationMessage::query()->where('direction', 'out')->count())->toBe(0);
});
