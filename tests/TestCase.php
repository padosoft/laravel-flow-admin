<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Padosoft\LaravelFlowAdmin\FlowAdminServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FlowAdminServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
