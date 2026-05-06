<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('flow-admin'));
        $this->assertSame('flow', config('flow-admin.prefix'));
        $this->assertSame('dark', config('flow-admin.theme_default'));
        $this->assertSame('timeline', config('flow-admin.step_viz_default'));
        $this->assertSame('eloquent', config('flow-admin.adapter'));
        $this->assertSame(4000, config('flow-admin.polling_interval_ms'));
    }

    public function test_view_namespace_is_registered(): void
    {
        $this->assertTrue(
            $this->app['view']->exists('flow-admin::pages.overview'),
            'View [flow-admin::pages.overview] must exist'
        );
    }

    public function test_route_is_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        $found = $routes->contains(function ($route) {
            return str_starts_with($route->uri(), 'flow')
                && $route->getName() === 'flow-admin.overview';
        });

        $this->assertTrue($found, 'Route [flow-admin.overview] must be registered');
    }
}
