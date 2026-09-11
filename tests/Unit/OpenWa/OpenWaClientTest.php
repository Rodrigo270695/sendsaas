<?php

declare(strict_types=1);

use App\Services\OpenWa\OpenWaClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
        'openwa.reconnect_poll_seconds' => 0,
    ]);
});

it('is configured only with enabled url and key', function (): void {
    $client = new OpenWaClient;

    expect($client->isConfigured())->toBeTrue();

    config(['openwa.api_key' => '']);

    expect($client->isConfigured())->toBeFalse();
});

it('envia texto a un chat', function (): void {
    Http::fake([
        'wa.test/api/sessions/ow-1/messages/send-text' => Http::response(['messageId' => 'mid-1']),
    ]);

    $result = (new OpenWaClient)->sendText('ow-1', '51999988877@c.us', 'Hola');

    expect($result['messageId'])->toBe('mid-1');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['chatId'] === '51999988877@c.us'
        && $request['text'] === 'Hola');
});

it('registra el webhook inbound de una sesion', function (): void {
    Http::fake([
        'wa.test/api/sessions/ow-1/webhooks' => Http::response(['id' => 'wh-1'], 201),
    ]);

    $result = (new OpenWaClient)->registerWebhook('ow-1', 'https://sendsaas.test/api/webhooks/openwa/demo', 'secret');

    expect($result['id'])->toBe('wh-1');
    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://wa.test/api/sessions/ow-1/webhooks'
            && $request['url'] === 'https://sendsaas.test/api/webhooks/openwa/demo'
            && $request['events'] === ['message.received'];
    });
});

it('obtiene el codigo qr de una sesion', function (): void {
    Http::fake([
        'wa.test/api/sessions/ow-1/qr' => Http::response([
            'qrCode' => 'data:image/png;base64,xx',
            'status' => 'qr_ready',
        ]),
    ]);

    $result = (new OpenWaClient)->getQrCode('ow-1');

    expect($result['qrCode'])->toBe('data:image/png;base64,xx');
    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://wa.test/api/sessions/ow-1/qr'
            && $request->header('X-API-Key')[0] === 'test-key';
    });
});
