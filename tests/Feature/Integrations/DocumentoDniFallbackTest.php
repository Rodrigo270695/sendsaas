<?php

use App\Services\Integrations\ApiPeruDniService;
use App\Services\Integrations\ApisunatLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('usa apisunat cuando apiperu devuelve 429', function () {
    Cache::flush();

    config()->set('services.apiperu.token', 'apiperu-token');
    config()->set('services.apiperu.base_url', 'https://apiperu.dev/api');
    config()->set('services.apisunat_lookup.token', 'lucode-token');
    config()->set('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1');

    Http::fake([
        'https://apiperu.dev/api/dni' => Http::response(['message' => 'Too Many Requests'], 429),
        'https://dev.apisunat.pe/api/v1/person/dni/12345678' => Http::response([
            'success' => true,
            'message' => 'OK',
            'payload' => [
                'dni' => '12345678',
                'nombres' => 'JUAN CARLOS',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'GARCIA',
                'nombre_completo' => 'PEREZ GARCIA JUAN CARLOS',
            ],
        ], 200),
    ]);

    $result = app(ApiPeruDniService::class)->consultar('12345678');

    expect($result['dni'])->toBe('12345678')
        ->and($result['nombres'])->toBe('JUAN CARLOS')
        ->and($result['apellidos'])->toBe('PEREZ GARCIA');
});

test('usa apisunat cuando apiperu hace timeout', function () {
    Cache::flush();

    config()->set('services.apiperu.token', 'apiperu-token');
    config()->set('services.apiperu.base_url', 'https://apiperu.dev/api');
    config()->set('services.apisunat_lookup.token', 'lucode-token');
    config()->set('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1');

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'apiperu.dev')) {
            throw new ConnectionException('cURL error 28: Connection timed out');
        }

        return Http::response([
            'success' => true,
            'message' => 'OK',
            'payload' => [
                'dni' => '77344506',
                'nombres' => 'ANA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => 'DIAZ',
                'nombre_completo' => 'LOPEZ DIAZ ANA',
            ],
        ], 200);
    });

    $result = app(ApiPeruDniService::class)->consultar('77344506');

    expect($result['dni'])->toBe('77344506')
        ->and($result['nombres'])->toBe('ANA')
        ->and($result['apellidos'])->toBe('LOPEZ DIAZ');
});

test('apisunat lookup parsea ruc', function () {
    config()->set('services.apisunat_lookup.token', 'lucode-token');
    config()->set('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1');

    Http::fake([
        'https://dev.apisunat.pe/api/v1/business/ruc/20553300429' => Http::response([
            'success' => true,
            'payload' => [
                'ruc' => '20553300429',
                'razon_social' => 'EMPRESA DEMO S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion_fiscal' => 'AV. DEMO 123',
            ],
        ], 200),
    ]);

    $result = app(ApisunatLookupService::class)->consultarRuc('20553300429');

    expect($result['razon_social'])->toBe('EMPRESA DEMO S.A.C.')
        ->and($result['direccion'])->toBe('AV. DEMO 123')
        ->and($result['estado_sunat'])->toBe('ACTIVO');
});
