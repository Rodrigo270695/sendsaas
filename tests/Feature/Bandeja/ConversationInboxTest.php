<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PermissionsSeeder::class);
    $this->seed(SuperadminSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->seed(DemoTenantsSeeder::class);
});

function bandejaAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

function seedInboxThread(string $name = 'Luis Demo', string $phone = '51999988877', int $unread = 2): Conversation
{
    $contact = Contact::factory()->create([
        'name' => $name,
        'phone' => $phone,
    ]);

    $conversation = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'status' => Conversation::STATUS_OPEN,
        'unread_count' => $unread,
        'last_message_at' => now(),
        'wa_chat_id' => $phone.'@c.us',
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $contact->id,
        'body' => 'Hola, quiero info',
        'external_id' => 'wamid.'.$conversation->id,
        'sent_at' => now()->subMinute(),
    ]);

    return $conversation;
}

test('tenant admin can open an empty inbox', function () {
    $this->actingAs(bandejaAdmin())
        ->get('http://demo.sendsaas.test/bandeja/conversaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bandeja/conversaciones/index')
            ->where('conversations.data', [])
            ->where('selected', null)
            ->where('stats.total', 0)
            ->where('stats.unread', 0)
            ->has('reply')
        );
});

test('user without permission cannot view the inbox', function () {
    $user = User::factory()->create(['tenant_id' => bandejaAdmin()->tenant_id]);

    $this->actingAs($user)
        ->get('http://demo.sendsaas.test/bandeja/conversaciones')
        ->assertForbidden();
});

test('inbox lists a conversation and opens the thread', function () {
    $conversation = seedInboxThread();

    $this->actingAs(bandejaAdmin())
        ->get('http://demo.sendsaas.test/bandeja/conversaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bandeja/conversaciones/index')
            ->has('conversations.data', 1)
            ->where('conversations.data.0.contact.name', 'Luis Demo')
            ->where('conversations.data.0.preview', 'Hola, quiero info')
            ->where('conversations.data.0.unread_count', 2)
            ->where('selected', null)
        );

    $this->actingAs(bandejaAdmin())
        ->get('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bandeja/conversaciones/index')
            ->where('selected.id', $conversation->id)
            ->where('selected.contact.name', 'Luis Demo')
            ->has('selected.messages', 1)
            ->where('selected.messages.0.body', 'Hola, quiero info')
            ->where('selected.unread_count', 0)
        );

    expect($conversation->fresh()->unread_count)->toBe(0);
});

test('inbox search filters by contact name', function () {
    seedInboxThread('Ana Pérez', '51911122233');
    seedInboxThread('Carlos Ruiz', '51944455566');

    $this->actingAs(bandejaAdmin())
        ->get('http://demo.sendsaas.test/bandeja/conversaciones?search=ana')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations.data', 1)
            ->where('conversations.data.0.contact.name', 'Ana Pérez')
        );
});
