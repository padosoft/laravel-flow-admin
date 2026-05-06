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

        // Drop `auth` from the admin route middleware in tests: the
        // package's default is `['web', 'auth']` (correct for production),
        // but Testbench's bundled Laravel app does not register a `login`
        // route, so a 302 from the `Authenticate` middleware would fan
        // out into a `RouteNotFoundException` on every Feature test that
        // calls a /flow URL. Tests for auth-gated mutations (resume,
        // reject, replay, cancel, retry-webhook) come in Macro 6 / 7 and
        // restore the full stack via test-local config overrides.
        $app['config']->set('flow-admin.middleware', ['web']);
    }
}
