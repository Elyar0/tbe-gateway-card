<?php

use Illuminate\Support\Facades\Route;
use TelegramBotEssentials\Essence\Http\Middleware\InitializeTenancyByPath;
use TelegramBotEssentials\GatewayCard\Http\Controllers\CardSmsWebhookController;

Route::prefix('{bot}/gateway-card')
    ->middleware([InitializeTenancyByPath::class, 'throttle:120,1'])
    ->group(function () {
        Route::post('/sms', [CardSmsWebhookController::class, 'sms'])->name('gateway-card.sms');
    });
