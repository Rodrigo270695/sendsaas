<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Tenant;
use App\Models\TenantWhatsappSession;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    config(['openwa.webhook_secret' => 'test-openwa-secret']);
});

function openWaWebhook(array $overrides = [], array $headers = []): array
{
    $payload = array_replace_recursive([
        'event' => 'message.received',
        'sessionId' => 'ow-demo-1',
        'data' => [
            'id' => 'wamid.test001',
            'body' => 'Hola, quiero info',
            'from' => '51999988877@c.us',
            'fromMe' => false,
            'type' => 'chat',
            'pushName' => 'Luis Demo',
        ],
    ], $overrides);

    $response = test()->postJson(
        'http://127.0.0.1/api/webhooks/openwa/demo',
        $payload,
        array_merge(['X-Webhook-Secret' => 'test-openwa-secret'], $headers),
    );

    return [$response, $payload];
}

test('rejects webhook without secret when configured', function () {
    $this->postJson('http://127.0.0.1/api/webhooks/openwa/demo', [
        'event' => 'message.received',
        'data' => ['body' => 'hola', 'from' => '51999988877@c.us', 'fromMe' => false],
    ])->assertUnauthorized();
});

test('returns 503 if webhook secret is missing', function () {
    config(['openwa.webhook_secret' => '']);

    $this->postJson('http://127.0.0.1/api/webhooks/openwa/demo', [
        'event' => 'message.received',
        'data' => ['body' => 'hola', 'from' => '51999988877@c.us', 'fromMe' => false],
    ], [
        'X-Webhook-Secret' => 'cualquier-cosa',
    ])->assertStatus(503)->assertJson(['error' => 'Webhook secret not configured']);
});

test('returns 404 for an unknown tenant slug', function () {
    $this->postJson('http://127.0.0.1/api/webhooks/openwa/no-existe', [
        'event' => 'message.received',
        'sessionId' => 'ow-x',
        'data' => [
            'id' => 'wamid.x',
            'body' => 'hola',
            'from' => '51999988877@c.us',
            'fromMe' => false,
            'type' => 'chat',
        ],
    ], [
        'X-Webhook-Secret' => 'test-openwa-secret',
    ])->assertNotFound();
});

test('ingests an inbound message into contact conversation and message', function () {
    $tenant = Tenant::query()->where('slug', DemoTenantsSeeder::SLUG)->firstOrFail();
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $tenant->id,
        'openwa_session_id' => 'ow-demo-1',
        'openwa_session_name' => 'ss-demo',
    ]);

    [$response] = openWaWebhook();

    $response->assertOk()->assertJson([
        'ok' => true,
        'ingested' => true,
        'duplicate' => false,
    ]);

    $contact = Contact::query()->where('phone', '51999988877')->first();
    expect($contact)->not->toBeNull()
        ->and($contact->name)->toBe('Luis Demo')
        ->and($contact->last_contact_at)->not->toBeNull();

    $conversation = Conversation::query()->where('contact_id', $contact->id)->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->status)->toBe(Conversation::STATUS_OPEN)
        ->and($conversation->unread_count)->toBe(1)
        ->and($conversation->wa_chat_id)->toBe('51999988877@c.us');

    $message = ConversationMessage::query()->where('external_id', 'wamid.test001')->first();
    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('Hola, quiero info')
        ->and($message->direction)->toBe(ConversationMessage::DIRECTION_IN)
        ->and($message->sender_type)->toBe(ConversationMessage::SENDER_CONTACT);
});

test('retries with the same external_id are idempotent', function () {
    $tenant = Tenant::query()->where('slug', DemoTenantsSeeder::SLUG)->firstOrFail();
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $tenant->id,
        'openwa_session_id' => 'ow-demo-1',
        'openwa_session_name' => 'ss-demo',
    ]);

    [$first] = openWaWebhook();
    [$second] = openWaWebhook();

    $first->assertOk()->assertJson(['ingested' => true]);
    $second->assertOk()->assertJson([
        'ingested' => false,
        'duplicate' => true,
    ]);

    expect(ConversationMessage::query()->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->first()?->unread_count)->toBe(1);
});

test('skips fromMe presence and groups', function () {
    $this->postJson('http://127.0.0.1/api/webhooks/openwa/demo', [
        'event' => 'presence',
        'sessionId' => 'ow-demo-1',
        'data' => ['from' => '51999988877@c.us'],
    ], [
        'X-Webhook-Secret' => 'test-openwa-secret',
    ])->assertOk()->assertJson(['skipped' => 'not_message_event']);

    [$fromMe] = openWaWebhook([
        'data' => ['fromMe' => true, 'id' => 'wamid.me'],
    ]);
    $fromMe->assertOk()->assertJson(['skipped' => 'from_me']);

    [$group] = openWaWebhook([
        'data' => [
            'id' => 'wamid.g',
            'from' => '12036399@g.us',
            'fromMe' => false,
        ],
    ]);
    $group->assertOk()->assertJson(['skipped' => 'group']);

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('skips a session that belongs to another tenant', function () {
    $other = Tenant::factory()->create(['slug' => 'acme', 'schema_name' => 'od_acme', 'estado' => 'active']);
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $other->id,
        'openwa_session_id' => 'ow-acme-1',
        'openwa_session_name' => 'ss-acme',
    ]);

    [$response] = openWaWebhook(['sessionId' => 'ow-acme-1']);

    $response->assertOk()->assertJson(['skipped' => 'tenant_mismatch']);
    expect(ConversationMessage::query()->count())->toBe(0);
});

test('syncs incoming openwa history into the inbox', function () {
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
    ]);

    $tenant = Tenant::query()->where('slug', DemoTenantsSeeder::SLUG)->firstOrFail();
    TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => $tenant->id,
        'openwa_session_id' => 'ow-demo-1',
        'openwa_session_name' => 'ss-demo',
    ]);

    Http::fake([
        'wa.test/api/sessions/ow-demo-1/messages*' => Http::response([
            'messages' => [
                [
                    'id' => 'db-1',
                    'waMessageId' => 'A5HOLA1',
                    'chatId' => '51976809804@c.us',
                    'chatName' => 'Orvae',
                    'from' => '51976809804@c.us',
                    'to' => '51976709811@c.us',
                    'body' => 'Hola',
                    'type' => 'text',
                    'direction' => 'incoming',
                ],
                [
                    'id' => 'db-2',
                    'waMessageId' => 'OUT1',
                    'chatId' => '51999988877@c.us',
                    'from' => '51976709811@c.us',
                    'body' => 'hola',
                    'type' => 'text',
                    'direction' => 'outgoing',
                ],
            ],
            'total' => 2,
        ]),
    ]);

    $this->artisan('sendsaas:openwa-sync-inbox', ['--slug' => 'demo', '--limit' => 10])
        ->assertSuccessful();

    $contact = Contact::query()->where('phone', '51976809804')->first();
    expect($contact)->not->toBeNull()
        ->and($contact->name)->toBe('Orvae');
    expect(ConversationMessage::query()->where('external_id', 'A5HOLA1')->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1);
});
