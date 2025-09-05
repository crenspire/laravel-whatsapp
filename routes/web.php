<?php

use Illuminate\Support\Facades\Route;
use Crenspire\Whatsapp\Http\Controllers\WhatsappWebhookController;

Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify'])
     ->name('whatsapp.webhook.verify');

Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
     ->name('whatsapp.webhook');
