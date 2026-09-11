<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\QuickReply;
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

function deskAdmin(): User
{
    return User::query()->where('email', DemoTenantsSeeder::EMAIL)->firstOrFail();
}

function deskAgent(): User
{
    $admin = deskAdmin();
    $agent = User::factory()->create([
        'tenant_id' => $admin->tenant_id,
        'name' => 'Agente Uno',
        'is_active' => true,
    ]);

    $previous = getPermissionsTeamId();
    setPermissionsTeamId($admin->tenant_id);

    try {
        $agent->assignRole('agente');
    } finally {
        setPermissionsTeamId($previous);
    }

    return $agent;
}

function seedDeskThread(string $name = 'Luis Demo', string $phone = '51999988877'): Conversation
{
    $contact = Contact::factory()->create([
        'name' => $name,
        'phone' => $phone,
    ]);

    $conversation = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'status' => Conversation::STATUS_OPEN,
        'unread_count' => 1,
        'last_message_at' => now(),
        'wa_chat_id' => $phone.'@c.us',
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $contact->id,
        'body' => 'Hola',
        'external_id' => 'wamid.'.$conversation->id,
        'sent_at' => now()->subMinute(),
    ]);

    return $conversation;
}

test('admin can assign a conversation to an agent', function () {
    $admin = deskAdmin();
    $agent = deskAgent();
    $conversation = seedDeskThread();

    $this->actingAs($admin)
        ->from('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->put('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/asignacion', [
            'assigned_user_id' => $agent->id,
        ])
        ->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBe($agent->id);
});

test('agent can take an unassigned conversation but cannot assign it to someone else', function () {
    $agent = deskAgent();
    $other = deskAgent();
    $conversation = seedDeskThread('Otro', '51911111111');

    $this->actingAs($agent)
        ->put('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/asignacion', [
            'assigned_user_id' => $agent->id,
        ])
        ->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBe($agent->id);

    $this->actingAs($agent)
        ->put('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/asignacion', [
            'assigned_user_id' => $other->id,
        ])
        ->assertForbidden();
});

test('agent cannot open a conversation assigned to someone else', function () {
    $admin = deskAdmin();
    $agent = deskAgent();
    $conversation = seedDeskThread();
    $conversation->forceFill(['assigned_user_id' => $admin->id])->save();

    $this->actingAs($agent)
        ->get('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->assertForbidden();

    $this->actingAs($agent)
        ->get('http://demo.sendsaas.test/bandeja/conversaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('conversations.data', []));
});

test('syncing tags attaches them to the conversation and the contact', function () {
    $conversation = seedDeskThread();

    $this->actingAs(deskAdmin())
        ->from('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id)
        ->put('http://demo.sendsaas.test/bandeja/conversaciones/'.$conversation->id.'/etiquetas', [
            'names' => ['vip', 'cotizacion'],
        ])
        ->assertRedirect();

    $conversation->refresh()->load(['tags', 'contact.tags']);

    expect($conversation->tags->pluck('name')->sort()->values()->all())->toBe(['COTIZACION', 'VIP'])
        ->and($conversation->contact?->tags->pluck('name')->sort()->values()->all())->toBe(['COTIZACION', 'VIP']);
});

test('supervisor can create and delete a quick reply', function () {
    $this->actingAs(deskAdmin())
        ->from('http://demo.sendsaas.test/bandeja/conversaciones')
        ->post('http://demo.sendsaas.test/bandeja/respuestas-rapidas', [
            'title' => 'Saludo',
            'shortcut' => 'hola',
            'body' => 'Hola {{nombre}}, ¿en qué te ayudo?',
        ])
        ->assertRedirect();

    $reply = QuickReply::query()->first();
    expect($reply)->not->toBeNull()
        ->and($reply->shortcut)->toBe('HOLA');

    $this->actingAs(deskAdmin())
        ->from('http://demo.sendsaas.test/bandeja/conversaciones')
        ->delete('http://demo.sendsaas.test/bandeja/respuestas-rapidas/'.$reply->id)
        ->assertRedirect();

    expect(QuickReply::query()->count())->toBe(0);
});

test('agent cannot manage quick replies', function () {
    $this->actingAs(deskAgent())
        ->post('http://demo.sendsaas.test/bandeja/respuestas-rapidas', [
            'title' => 'Saludo',
            'body' => 'Hola',
        ])
        ->assertForbidden();
});

test('inbox includes assignment catalog and filters mine', function () {
    $admin = deskAdmin();
    $mine = seedDeskThread('Mia', '51922233344');
    $mine->forceFill(['assigned_user_id' => $admin->id])->save();
    seedDeskThread('Libre', '51955566677');

    $this->actingAs($admin)
        ->get('http://demo.sendsaas.test/bandeja/conversaciones?assigned=mias')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bandeja/conversaciones/index')
            ->has('conversations.data', 1)
            ->where('conversations.data.0.contact.name', 'Mia')
            ->has('assignees')
            ->has('quick_replies')
            ->where('capabilities.assign', true)
        );
});
