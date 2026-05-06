<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin;

use Illuminate\Support\ServiceProvider;

class FlowAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flow-admin.php',
            'flow-admin'
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'flow-admin');

        $this->loadRoutesFrom(__DIR__ . '/../routes/flow-admin.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/flow-admin.php' => config_path('flow-admin.php'),
            ], 'flow-admin-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/flow-admin'),
            ], 'flow-admin-views');

            $this->publishes([
                __DIR__ . '/../resources/css' => public_path('vendor/flow-admin/css'),
                __DIR__ . '/../resources/js' => public_path('vendor/flow-admin/js'),
            ], 'flow-admin-assets');
        }
    }
}
