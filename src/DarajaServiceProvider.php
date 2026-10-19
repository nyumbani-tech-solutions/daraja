<?php

namespace Nyumbani\Daraja;

use Illuminate\Support\ServiceProvider;

class DarajaServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function register(): void
    {
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

        //  Load the routes when enabled
        if (config('daraja.routes.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/daraja.php');
        }
    }
}
