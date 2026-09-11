<?php

use App\Support\OpenWa\OpenWaInboundPayload;

it('normalizes a peruvian whatsapp inbound payload', function () {
    $payload = OpenWaInboundPayload::fromRequest([
        'event' => 'message.received',
        'sessionId' => 'ow-1',
        'data' => [
            'id' => 'wamid.abc',
            'body' => 'Hola',
            'from' => '51987654321@c.us',
            'fromMe' => false,
            'type' => 'chat',
            'pushName' => 'Ana',
        ],
    ]);

    expect($payload->phone)->toBe('51987654321')
        ->and($payload->name)->toBe('Ana')
        ->and($payload->body)->toBe('Hola')
        ->and($payload->messageType)->toBe('text')
        ->and($payload->externalId)->toBe('wamid.abc')
        ->and($payload->isGroup())->toBeFalse()
        ->and($payload->fromMe)->toBeFalse();
});

it('skips groups and linked ids without a real phone', function () {
    $group = OpenWaInboundPayload::fromRequest([
        'event' => 'message.received',
        'data' => ['from' => '120363@g.us', 'body' => 'hola', 'id' => 'g1'],
    ]);
    $lid = OpenWaInboundPayload::fromRequest([
        'event' => 'message.received',
        'data' => ['from' => '123456789012345@lid', 'body' => 'hola', 'id' => 'l1'],
    ]);

    expect($group->isGroup())->toBeTrue()
        ->and($group->phone)->toBeNull()
        ->and($lid->isGroup())->toBeFalse()
        ->and($lid->phone)->toBe('lid:123456789012345');
});

it('prefers a real phone when the chat arrives as lid plus senderPn', function () {
    $payload = OpenWaInboundPayload::fromRequest([
        'event' => 'message.received',
        'data' => [
            'from' => '999888777666555@lid',
            'senderPn' => '51987654321@c.us',
            'body' => 'Hola',
            'id' => 'l2',
        ],
    ]);

    expect($payload->phone)->toBe('51987654321')
        ->and($payload->waChatId)->toBe('999888777666555@lid');
});
