<?php

namespace Crenspire\Whatsapp;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * WhatsApp Service Provider
 *
 * This service provider registers the WhatsApp service with the Laravel container
 * and handles package configuration and route loading using Spatie's package tools.
 *
 * @package Crenspire\Whatsapp
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class WhatsappServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package
     *
     * @param Package $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-whatsapp')
            ->hasConfigFile('whatsapp')
            ->hasRoute('web');
    }

    /**
     * Register services in the container
     *
     * @return void
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService(config('whatsapp'));
        });
    }
}
