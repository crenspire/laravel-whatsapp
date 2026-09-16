<?php

namespace Crenspire\Whatsapp;

use Crenspire\Whatsapp\Console\CheckCommand;
use Crenspire\Whatsapp\Console\TemplatesCommand;
use Crenspire\Whatsapp\Console\TestCommand;
use Crenspire\Whatsapp\Listeners\LogMessages;
use Crenspire\Whatsapp\Notifications\WhatsappChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers the WhatsApp service, webhook routes, notification channel and commands
 */
class WhatsappServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-whatsapp')
            ->hasConfigFile('whatsapp')
            ->hasRoute('web')
            ->hasMigration('create_whatsapp_messages_table')
            ->hasCommands([
                CheckCommand::class,
                TemplatesCommand::class,
                TestCommand::class,
            ]);
    }

    /**
     * Register services in the container
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService($app['config']->get('whatsapp'));
        });
    }

    /**
     * Register the notification channel and message log
     */
    public function packageBooted(): void
    {
        Notification::resolved(function (ChannelManager $channels) {
            $channels->extend('whatsapp', fn ($app) => $app->make(WhatsappChannel::class));
        });

        if ($this->app['config']->get('whatsapp.message_log.enabled')) {
            Event::subscribe(LogMessages::class);
        }
    }
}
