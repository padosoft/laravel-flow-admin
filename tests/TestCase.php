<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Padosoft\LaravelFlow\LaravelFlowServiceProvider;
use Padosoft\LaravelFlowAdmin\FlowAdminServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelFlowServiceProvider::class,
            FlowAdminServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // The `web` middleware group includes `EncryptCookies` and
        // `StartSession`, both of which require APP_KEY at request time.
        // Testbench's bundled .env carries one for `serve`, but PHPUnit
        // does not load that .env, so without an explicit key the entire
        // Feature suite fails with `MissingAppKeyException`. The value
        // is a deterministic 32-byte test key — never a real secret;
        // exactly 32 bytes is required by AES-256-CBC.
        $app['config']->set('app.key', 'base64:' . base64_encode(str_pad('flowadmin-test', 32, '_')));

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
