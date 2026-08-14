<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('telegram/webhook', TelegramWebhookController::class)
    ->middleware('throttle:30,1')
    ->name('telegram.webhook');
