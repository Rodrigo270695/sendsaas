<?php

use App\Support\OpenWa\OpenWaWebhookEvents;

it('accepts inbound chat events and rejects acks', function () {
    expect(OpenWaWebhookEvents::isInboundChat('message.received'))->toBeTrue()
        ->and(OpenWaWebhookEvents::isInboundChat('onMessage'))->toBeTrue()
        ->and(OpenWaWebhookEvents::isInboundChat('message.ack'))->toBeFalse()
        ->and(OpenWaWebhookEvents::isInboundChat('presence'))->toBeFalse()
        ->and(OpenWaWebhookEvents::inboundMessageSubscriptions())->toBe(['message.received']);
});
