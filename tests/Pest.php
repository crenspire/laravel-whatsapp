<?php

use Crenspire\Whatsapp\Tests\TestCase;

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
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'debug' => false,
    ], $overrides);
}

/**
 * Create a service instance using the test configuration.
 */
function makeService(array $overrides = []): \Crenspire\Whatsapp\WhatsappService
{
    return new \Crenspire\Whatsapp\WhatsappService(whatsappConfig($overrides));
}
