<?php

use Crenspire\Whatsapp\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');

Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
    ->name('whatsapp.webhook');
