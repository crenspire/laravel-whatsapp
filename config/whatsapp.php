<?php

return [

    'base_uri' => env('WHATSAPP_BASE_URI', 'https://graph.facebook.com/v20.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    // Default headers for all requests
    'default_headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'User-Agent' => 'Laravel-WhatsApp-Package/1.0.0',
    ],

    // Default template language (Meta language code, e.g. en_US, en, pt_BR)
    'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en_US'),

    // Per-tenant routing
    'tenants' => [
        // 'tenant_id' => [
        //     'phone_number_id' => '...', 
        //     'access_token' => '...',
        //     'business_account_id' => '...', // Optional business account ID per tenant
        //     'headers' => ['Custom-Header' => 'value'], // Optional custom headers per tenant
        //     'language' => 'es_ES' // Optional language override per tenant
        // ],
    ],

    // Rate limit per minute
    'rate_limit' => env('WHATSAPP_RATE_LIMIT', 30),

    // Storage path for media downloads
    'media_storage' => storage_path('app/whatsapp-media'),

    // Debug mode
    'debug' => env('WHATSAPP_DEBUG', false),

];
