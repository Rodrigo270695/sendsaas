<?php

return [

    'drip_enabled' => (bool) env('OUTBOUND_DRIP_ENABLED', true),

    /** Tope del spec: 1 envío drip por minuto. */
    'min_interval_seconds' => (int) env('OUTBOUND_MIN_INTERVAL_SECONDS', 60),

    /** Jitter ±20 % sobre el intervalo calculado. */
    'jitter' => (float) env('OUTBOUND_DRIP_JITTER', 0.2),

    /** Pausa entre sendText globales para no tumbar OpenWA. */
    'openwa_gap_seconds' => (int) env('OUTBOUND_OPENWA_GAP_SECONDS', 3),

    /** Si un reserved no termina, vuelve a queued. */
    'reserve_timeout_seconds' => (int) env('OUTBOUND_RESERVE_TIMEOUT_SECONDS', 120),

    'max_attempts' => (int) env('OUTBOUND_MAX_ATTEMPTS', 5),

    'retry_delay_seconds' => (int) env('OUTBOUND_RETRY_DELAY_SECONDS', 120),

];
