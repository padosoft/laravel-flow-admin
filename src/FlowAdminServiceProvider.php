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

        $this->publishes([
            __DIR__ . '/../config/flow-admin.php' => config_path('flow-admin.php'),
        ], 'flow-admin-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/flow-admin'),
        ], 'flow-admin-views');
    }
}
