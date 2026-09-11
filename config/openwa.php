<?php

return [

    'enabled' => (bool) env('OPENWA_ENABLED', false),

    'api_url' => rtrim((string) env('OPENWA_API_URL', ''), '/'),

    'api_key' => env('OPENWA_API_KEY'),

    'admin_url' => rtrim((string) env('OPENWA_ADMIN_URL', ''), '/'),

    'timeout_seconds' => (int) env('OPENWA_TIMEOUT_SECONDS', 45),

    'reconnect_poll_seconds' => (int) env('OPENWA_RECONNECT_POLL_SECONDS', 3),

    'webhook_secret' => env('OPENWA_WEBHOOK_SECRET'),

    // Base sin slug. Default: {APP_URL}/api/webhooks/openwa/{slug}
    'webhook_base_url' => rtrim((string) env('OPENWA_WEBHOOK_BASE_URL', ''), '/'),

];
