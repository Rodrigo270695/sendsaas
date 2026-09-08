<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integración Orvae PE → SendSaaS
    |--------------------------------------------------------------------------
    |
    | Orvae solo cobra. Este hijo firma POST /api/internal/saas/{provision,renew}
    | con HMAC-SHA256("{unix_ts}.{raw_json_body}", secret).
    | El mismo secret vive en Orvae como SENDSAAS_PROVISION_HMAC_SECRET.
    |
    */

    'provision' => [
        'hmac_secret' => env('ORVAE_PROVISION_HMAC_SECRET'),
        'max_skew_seconds' => (int) env('ORVAE_PROVISION_MAX_SKEW_SECONDS', 300),
        'idempotency_ttl_days' => (int) env('ORVAE_PROVISION_IDEMPOTENCY_TTL_DAYS', 30),
    ],

    'tenant' => [
        'scheme' => env('SENDSAAS_TENANT_SCHEME', 'https'),
        'domain' => env('SENDSAAS_TENANT_DOMAIN', env('TENANT_ROOT_DOMAIN', 'sendsaas.orvae.pe')),
        'login_path' => env('SENDSAAS_TENANT_LOGIN_PATH', '/login'),
        'bootstrap_ttl_hours' => (int) env('SENDSAAS_BOOTSTRAP_TTL_HOURS', 48),
    ],
];
