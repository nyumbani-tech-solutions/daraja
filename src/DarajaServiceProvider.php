<?php

namespace Nyumbani\Daraja;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nyumbani\Daraja\Docs\DarajaDemos;
use Nyumbani\Daraja\Docs\ReceivedHookController;
use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;
use Nyumbani\Docs\Docs;
use Nyumbani\Docs\DocsRegistry;
use Nyumbani\Docs\Http\Middleware\EnsureDocsAreAvailable;

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

        //  Add the docs, which Nyumbani Docs serves, when it is installed
        if (class_exists(DocsRegistry::class)) {
            $this->registerDocs();
        }
    }

    /**
     * Add the Daraja docs to the Nyumbani docs, which Nyumbani Docs serves
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function registerDocs(): void
    {
        //  Load the views of the pages
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'daraja');

        //  Describe the pages
        $demos = new DarajaDemos;

        $this->app->make(DocsRegistry::class)->register((new Docs('nyumbani-daraja', 'Daraja', [
            ['title' => 'Getting started', 'pages' => [
                'introduction' => 'Introduction',
                'installation' => 'Installation',
                'configuration' => 'Configuration',
                'walkthrough' => 'Walkthrough',
            ]],
            ['title' => 'Using Daraja', 'pages' => [
                'payments' => 'Payments',
                'webhooks' => 'Webhooks',
            ]],
        ], 'daraja::docs'))
            ->demo('phone', $demos->phone(...))
            ->demo('short-code', $demos->shortCode(...))
            ->demo('stk-push', $demos->stkPush(...))
            ->demo('register-c2b', $demos->registerC2b(...))
            ->demo('register-pull', $demos->registerPull(...))
            ->demo('pull', $demos->pull(...))
            ->demo('guard', $demos->guard(...))
            ->demo('live-token', $demos->liveToken(...))
            ->demo('live-stk-push', $demos->liveStkPush(...))
            ->demo('live-register-c2b', $demos->liveRegisterC2b(...))
            ->demo('live-register-pull', $demos->liveRegisterPull(...))
            ->demo('live-pull', $demos->livePull(...))
            ->demo('received', $demos->received(...)));

        //  Receive what Safaricom posts to the live buttons of the docs, which only its addresses may reach
        Route::post('nyumbani-daraja/docs/samples/hooks/{hook}', ReceivedHookController::class)
            ->whereIn('hook', ['stk', 'validation', 'confirmation', 'pull'])
            ->middleware(['web', EnsureDocsAreAvailable::class, EnsureRequestIsFromSafaricom::class])
            ->withoutMiddleware(['Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery', 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken'])
            ->name('daraja.docs.hook');
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
