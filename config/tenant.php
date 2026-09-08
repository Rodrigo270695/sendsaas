<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema PostgreSQL al migrar el tenant
    |--------------------------------------------------------------------------
    |
    | Lo setea en runtime el comando de migrate de tenant. Vacío fuera de eso
    | para que `php artisan migrate` no toque schemas de cliente.
    |
    */
    'migration_schema' => env('TENANT_MIGRATION_SCHEMA'),

    /*
    |--------------------------------------------------------------------------
    | Dominios centrales (superadmin / landing)
    |--------------------------------------------------------------------------
    |
    | Estos hosts NUNCA se interpretan como tenant. El panel de plataforma
    | vive aquí. Cada empresa entra por {slug}.{root_domain}.
    |
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANT_CENTRAL_DOMAINS', 'localhost,127.0.0.1,sendsaas.test,sendsaas.orvae.pe'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Dominio raíz de subdominios de tenant
    |--------------------------------------------------------------------------
    |
    | Host `empresa-ana.sendsaas.orvae.pe` → slug `empresa-ana`.
    | DNS wildcard: *.sendsaas.orvae.pe → mismo VPS.
    |
    */
    'root_domain' => env('TENANT_ROOT_DOMAIN', 'sendsaas.orvae.pe'),

    /*
    | Prefijo de schema físico (od_ + 6 chars). Vacío = sin validar prefijo.
    */
    'schema_prefix' => env('TENANT_SCHEMA_PREFIX', 'od_'),

    'allowed_states' => ['active', 'trial', 'grace'],

    'cache_ttl' => (int) env('TENANT_CACHE_TTL', 60),
];
