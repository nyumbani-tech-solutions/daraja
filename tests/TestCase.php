<?php

namespace Nyumbani\Daraja\Tests;

use Nyumbani\Daraja\DarajaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Load the package
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function getPackageProviders($app): array
    {
        return [DarajaServiceProvider::class];
    }

    /**
     * Enable the routes and set the key signed urls need
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('daraja.routes.enabled', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
