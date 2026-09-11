<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sendsaas:outbound-drip')
    ->everyThirtySeconds()
    ->withoutOverlapping(5)
    ->when(fn () => (bool) config('outbound.drip_enabled', true));

Schedule::command('sendsaas:openwa-sync-inbox --limit=30')
    ->everyMinute()
    ->withoutOverlapping(5);
