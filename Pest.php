<?php

use Crenspire\Whatsapp\WhatsappServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)->in('.');

beforeEach(function () {
    $this->app->register(WhatsappServiceProvider::class);
});

function getPackageProviders($app)
{
    return [
        WhatsappServiceProvider::class,
    ];
}

function getEnvironmentSetUp($app)
{
    $app['config']->set('whatsapp', [
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'phone_number_id' => 'test_phone_number',
        'access_token' => 'test_token',
        'webhook_verify_token' => 'test_verify_token',
        'webhook_secret' => null,
        'tenants' => [],
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'debug' => false,
    ]);
}
