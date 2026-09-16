<?php

return [

    'base_uri' => env('WHATSAPP_BASE_URI', 'https://graph.facebook.com/v20.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    // Meta app ID, needed to upload sample media for template headers
    'app_id' => env('WHATSAPP_APP_ID'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    'webhook' => [
        // Skip webhook events that were already processed. Meta retries
        // deliveries and can send the same event more than once.
        'deduplicate' => env('WHATSAPP_WEBHOOK_DEDUPLICATE', true),

        // How long to remember processed events, in minutes
        'deduplicate_for' => 1440,

        // Cache store used to remember events (null uses the default store)
        'cache_store' => env('WHATSAPP_WEBHOOK_CACHE_STORE'),
    ],

    // Default headers for all requests
    'default_headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'User-Agent' => 'Laravel-WhatsApp-Package/3.0.0',
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

    // Request timeout in seconds
    'timeout' => env('WHATSAPP_TIMEOUT', 30),

    // Retry connection errors, rate limits and temporary Meta errors
    'retry' => [
        // Total attempts per request (1 disables retries)
        'times' => env('WHATSAPP_RETRY_TIMES', 3),

        // Delay before the first retry in milliseconds, doubled on each retry
        'sleep' => env('WHATSAPP_RETRY_SLEEP', 200),
    ],

    // Store sent and received messages in the database. Publish and run the
    // migration first: php artisan vendor:publish --tag=whatsapp-migrations
    'message_log' => [
        'enabled' => env('WHATSAPP_MESSAGE_LOG', false),

        // Database connection (null uses the default connection)
        'connection' => env('WHATSAPP_MESSAGE_LOG_CONNECTION'),
    ],

    // Storage path for media downloads
    'media_storage' => storage_path('app/whatsapp-media'),

    // Debug mode
    'debug' => env('WHATSAPP_DEBUG', false),

];
