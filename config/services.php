<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Perú (consulta DNI / RUC vía apiperu.dev)
    |--------------------------------------------------------------------------
    */
    'apiperu' => [
        'base_url' => env('APIPERU_BASE_URL', 'https://apiperu.dev/api'),
        'token' => env('APIPERU_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | APISUNAT / Lucode (respaldo DNI / RUC)
    |--------------------------------------------------------------------------
    */
    'apisunat_lookup' => [
        'base_url' => env('APISUNAT_LOOKUP_BASE_URL', 'https://dev.apisunat.pe/api/v1'),
        'token' => env('APISUNAT_LOOKUP_TOKEN'),
    ],

];
