<?php

use App\Http\Controllers\Api\OpenWaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/openwa/{slug}', [OpenWaWebhookController::class, 'handle'])
    ->name('api.webhooks.openwa');
