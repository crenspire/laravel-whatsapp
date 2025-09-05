<?php

return [

    'base_uri' => env('WHATSAPP_BASE_URI', 'https://graph.facebook.com/v20.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    // Per-tenant routing
    'tenants' => [
        // 'tenant_id' => ['phone_number_id' => '...', 'access_token' => '...'],
    ],

    // Rate limit per minute
    'rate_limit' => env('WHATSAPP_RATE_LIMIT', 30),

    // Storage path for media downloads
    'media_storage' => storage_path('app/whatsapp-media'),

    // Debug mode
    'debug' => env('WHATSAPP_DEBUG', false),

];
