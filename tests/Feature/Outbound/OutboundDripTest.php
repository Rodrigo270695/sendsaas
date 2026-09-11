<?php

use App\Models\Contact;
use App\Models\ConversationMessage;
use App\Models\OutboundQueueItem;
use App\Models\TenantWhatsappSession;
use App\Models\UsageRecord;
use App\Models\User;
use App\Support\Outbound\DripPacing;
use App\Support\Outbound\SendWindow;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);

    Cache::flush();
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
        'outbound.drip_enabled' => true,
        'outbound.jitter' => 0,
        'outbound.min_interval_seconds' => 60,
        'outbound.openwa_gap_seconds' => 1,
    ]);

    $this->travelTo(Carbon::parse('2026-09-11 10:00:00', 'America/Lima'));
});

function dripAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

function seedDripReadySession(): TenantWhatsappSession
{
    return TenantWhatsappSession::factory()->ready()->create([
        'tenant_id' => dripAdmin()->tenant_id,
        'openwa_session_id' => 'ow-drip-1',
        'openwa_session_name' => 'ss-demo',
    ]);
}

function seedQueuedDrip(string $phone = '51988877766', string $body = 'Promo drip'): OutboundQueueItem
{
    $contact = Contact::factory()->create([
        'name' => 'Ana Drip',
        'phone' => $phone,
    ]);

    return OutboundQueueItem::factory()->queued()->create([
        'contact_id' => $contact->id,
        'contact_phone' => $phone,
        'chat_id' => $phone.'@c.us',
        'body' => $body,
        'kind' => OutboundQueueItem::KIND_CAMPAIGN,
    ]);
}

test('drip worker sends one queued message inside the window and counts quota', function () {
    Http::fake([
        'wa.test/api/sessions/ow-drip-1/messages/send-text' => Http::response(['messageId' => 'wamid.drip-1']),
    ]);
    seedDripReadySession();
    $item = seedQueuedDrip();

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OutboundQueueItem::STATUS_SENT)
        ->and($item->fresh()->sent_at)->not->toBeNull();

    expect(ConversationMessage::query()->where('direction', 'out')->value('body'))->toBe('Promo drip');
    expect(UsageRecord::query()->where('metric', UsageRecord::METRIC_OUTBOUND_DAY)->value('used'))->toBe(1);

    Http::assertSent(fn ($request) => $request['chatId'] === '51988877766@c.us'
        && $request['text'] === 'Promo drip');
});

test('drip worker does not send outside the send window', function () {
    Http::fake();
    seedDripReadySession();
    $item = seedQueuedDrip();
    $this->travelTo(Carbon::parse('2026-09-11 03:00:00', 'America/Lima'));

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OutboundQueueItem::STATUS_QUEUED);
    Http::assertNothingSent();
});

test('drip worker pauses when the daily quota is exhausted', function () {
    Http::fake();
    seedDripReadySession();
    $item = seedQueuedDrip();
    $admin = dripAdmin();

    UsageRecord::query()->create([
        'tenant_id' => $admin->tenant_id,
        'metric' => UsageRecord::METRIC_OUTBOUND_DAY,
        'period' => now('America/Lima')->toDateString(),
        'used' => 500,
    ]);

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OutboundQueueItem::STATUS_QUEUED);
    Http::assertNothingSent();
});

test('second drip tick waits for the interval', function () {
    Http::fake([
        'wa.test/api/sessions/ow-drip-1/messages/send-text' => Http::response(['messageId' => 'wamid.drip-2']),
    ]);
    seedDripReadySession();
    $first = seedQueuedDrip('51911111111', 'Uno');
    $first->forceFill(['available_at' => now()->subMinutes(2)])->save();
    $second = seedQueuedDrip('51922222222', 'Dos');
    $second->forceFill(['available_at' => now()->subMinute()])->save();

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])->assertSuccessful();
    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])->assertSuccessful();

    expect($first->fresh()->status)->toBe(OutboundQueueItem::STATUS_SENT)
        ->and($second->fresh()->status)->toBe(OutboundQueueItem::STATUS_QUEUED);
});

test('openwa 429 cools down and leaves the item queued', function () {
    Http::fake([
        'wa.test/api/sessions/ow-drip-1/messages/send-text' => Http::response('rate limited', 429),
    ]);
    seedDripReadySession();
    $item = seedQueuedDrip();

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OutboundQueueItem::STATUS_QUEUED)
        ->and(Cache::has('openwa:cooldown'))->toBeTrue();
});

test('exhausted attempts mark the item failed', function () {
    Http::fake([
        'wa.test/api/sessions/ow-drip-1/messages/send-text' => Http::response('bad request', 400),
    ]);
    config(['outbound.max_attempts' => 1]);
    seedDripReadySession();
    $item = seedQueuedDrip();

    $this->artisan('sendsaas:outbound-drip', ['--slug' => DemoTenantsSeeder::SLUG])
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OutboundQueueItem::STATUS_FAILED)
        ->and($item->fresh()->last_error)->not->toBeNull();
});

test('scheduled sends page lists queued items', function () {
    $item = seedQueuedDrip();

    $this->actingAs(dripAdmin())
        ->get('http://demo.sendsaas.test/comunicaciones/envios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('comunicaciones/envios/index')
            ->where('stats.queued', 1)
            ->where('items.data.0.id', $item->id)
            ->where('items.data.0.status', 'queued')
        );
});

test('send window and interval match the starter plan', function () {
    $tenant = dripAdmin()->tenant;
    expect($tenant)->not->toBeNull();
    $tenant->loadMissing('plan');

    expect(SendWindow::isOpen($tenant, Carbon::parse('2026-09-11 10:00:00', 'America/Lima')))->toBeTrue()
        ->and(SendWindow::isOpen($tenant, Carbon::parse('2026-09-11 21:00:00', 'America/Lima')))->toBeFalse()
        ->and(SendWindow::durationSeconds($tenant->plan))->toBe(12 * 3600)
        ->and(DripPacing::intervalSeconds($tenant, 500))->toBe(87);
});
