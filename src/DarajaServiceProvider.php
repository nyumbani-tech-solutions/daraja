<?php

namespace Nyumbani\Daraja;

use Illuminate\Support\ServiceProvider;
use Nyumbani\Essentials\Docs\Docs;
use Nyumbani\Essentials\Docs\DocsRegistry;

class DarajaServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function register(): void
    {
        //  Load helpers
        $this->loadHelpersFrom(__DIR__.'/Utils');

        //  Load the configuration file
        $this->mergeConfigFrom(__DIR__.'/../config/daraja.php', 'daraja');
    }

    /**
     * Bootstrap the package services.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function boot(): void
    {
        //  Publish the configuration file
        $this->publishes([
            __DIR__.'/../config/daraja.php' => config_path('daraja.php'),
        ], 'daraja-config');

        //  Add the docs when Nyumbani Essentials is installed to show them
        $this->registerDocs();
    }

    /**
     * Add the Daraja docs to the Nyumbani docs, which Essentials serves
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function registerDocs(): void
    {
        //  Skip when Essentials is not installed
        if (! class_exists(DocsRegistry::class)) {
            return;
        }

        //  Load the views of the pages
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'daraja');

        //  Describe the pages
        $this->app->make(DocsRegistry::class)->register(new Docs('nyumbani-daraja', 'Daraja', [
            ['title' => 'Getting started', 'pages' => [
                'introduction' => 'Introduction',
                'installation' => 'Installation',
                'configuration' => 'Configuration',
            ]],
            ['title' => 'Using Daraja', 'pages' => [
                'payments' => 'Payments',
                'webhooks' => 'Webhooks',
            ]],
        ], 'daraja::docs'));
    }

    /**
     * Add any package helpers to the application.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function loadHelpersFrom(string $path): void
    {
        foreach (glob("$path/*.php") as $helper) {
            require_once $helper;
        }
    }
}
