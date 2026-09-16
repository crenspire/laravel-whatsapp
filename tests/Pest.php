<?php

use Crenspire\Whatsapp\Tests\TestCase;
use Crenspire\Whatsapp\WhatsappService;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a package configuration array for tests.
 */
function whatsappConfig(array $overrides = []): array
{
    return array_replace([
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'business_account_id' => 'waba_123',
        'webhook_verify_token' => 'test_verify_token',
        'webhook_secret' => null,
        'default_headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'default_language' => 'en_US',
        'tenants' => [],
        'rate_limit' => 30,
        'timeout' => 30,
        'retry' => ['times' => 3, 'sleep' => 0],
        'webhook' => ['deduplicate' => true, 'deduplicate_for' => 1440, 'cache_store' => null],
        'message_log' => ['enabled' => false, 'connection' => null],
        'app_id' => 'app_123',
        'media_storage' => sys_get_temp_dir().'/whatsapp-media',
        'debug' => false,
    ], $overrides);
}

/**
 * Create a service instance using the test configuration.
 */
function makeService(array $overrides = []): WhatsappService
{
    return new WhatsappService(whatsappConfig($overrides));
}

/**
 * Build a webhook payload from entries.
 */
function webhookPayload(array $entries): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => $entries];
}

/**
 * Build a webhook entry with one messages change.
 */
function webhookEntry(array $value): array
{
    return ['id' => 'waba_123', 'changes' => [['field' => 'messages', 'value' => $value]]];
}
