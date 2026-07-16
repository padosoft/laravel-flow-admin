<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlow\Node\NodeRegistry;
use Padosoft\LaravelFlowAdmin\Adapters\ArrayReadModel;
use Padosoft\LaravelFlowAdmin\Adapters\EloquentReadModel;
use Padosoft\LaravelFlowAdmin\Authorizers\AllowAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Authorizers\DenyAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('flow-admin'));
        // Assert the config keys exist rather than their env-driven default values,
        // so tests do not break when contributors have FLOW_ADMIN_* env vars set locally.
        $this->assertArrayHasKey('prefix', config('flow-admin'));
        $this->assertArrayHasKey('middleware', config('flow-admin'));
        $this->assertArrayHasKey('adapter', config('flow-admin'));
        $this->assertArrayHasKey('authorizer', config('flow-admin'));
        $this->assertArrayHasKey('polling_interval_ms', config('flow-admin'));
        $this->assertArrayHasKey('theme_default', config('flow-admin'));
        $this->assertArrayHasKey('step_viz_default', config('flow-admin'));
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
        $prefix = config('flow-admin.prefix', 'flow');

        $found = $routes->contains(function ($route) use ($prefix) {
            return $route->uri() === $prefix
                && $route->getName() === 'flow-admin.overview';
        });

        $this->assertTrue($found, "Route [flow-admin.overview] with URI [{$prefix}] must be registered");
    }

    public function test_read_model_binding_defaults_to_eloquent_adapter(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'eloquent');

        $readModel = $this->app->make(ReadModel::class);

        $this->assertInstanceOf(EloquentReadModel::class, $readModel);
    }

    public function test_read_model_binding_switches_to_array_adapter(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $readModel = $this->app->make(ReadModel::class);

        $this->assertInstanceOf(ArrayReadModel::class, $readModel);
    }

    public function test_authorizer_binding_defaults_to_deny_all(): void
    {
        $this->app['config']->set('flow-admin.authorizer', DenyAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);

        $authorizer = $this->app->make(ActionAuthorizer::class);
        $this->assertInstanceOf(DenyAllAuthorizer::class, $authorizer);

        try {
            Authorize::action('view_runs', fn (): bool => true);

            $this->fail('Expected action() to throw HttpException');
        } catch (HttpException $exception) {
            $this->assertSame('Action not authorized', $exception->getMessage());
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_demo_nodes_are_not_registered_when_adapter_is_eloquent(): void
    {
        // TestCase's default environment config never sets flow-admin.adapter,
        // so it resolves to the package default ('eloquent') at the time
        // registerDemoNodesIfInDemoMode() ran during this app's boot — the
        // registry it populated must NOT carry the array-adapter's demo
        // fixture node types, proving a production Eloquent deployment's
        // NodeRegistry stays free of them.
        $registry = $this->app->make(NodeRegistry::class);

        foreach (['demo.trigger', 'demo.validate', 'demo.charge', 'demo.notify'] as $type) {
            $this->assertFalse($registry->has($type), "NodeRegistry must not have [{$type}] registered outside array adapter mode");
        }
    }

    public function test_allow_all_authorizer_is_refused_in_the_production_environment(): void
    {
        $this->app['config']->set('flow-admin.authorizer', AllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);
        $this->app['env'] = 'production';

        $authorizer = $this->app->make(ActionAuthorizer::class);

        $this->assertInstanceOf(DenyAllAuthorizer::class, $authorizer);
    }

    public function test_allow_all_authorizer_is_honored_outside_production(): void
    {
        $this->app['config']->set('flow-admin.authorizer', AllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);

        $authorizer = $this->app->make(ActionAuthorizer::class);

        $this->assertInstanceOf(AllowAllAuthorizer::class, $authorizer);
    }
}
