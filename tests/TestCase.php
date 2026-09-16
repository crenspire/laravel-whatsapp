<?php

namespace Crenspire\Whatsapp\Tests;

use Crenspire\Whatsapp\WhatsappServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach the real Graph API
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [
            WhatsappServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('whatsapp', whatsappConfig());
    }
}
