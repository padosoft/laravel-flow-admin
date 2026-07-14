<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Pins that the Studio canvas/JSON-API routes are wired into the SAME
 * middleware group as every other admin page — unlike the pre-auth static
 * asset routes (`AssetRouteTest`/`StudioAssetRouteTest`), `graph()` returns
 * flow definition data and must never be reachable outside the configured
 * `flow-admin.middleware` stack.
 */
final class StudioControllerTest extends TestCase
{
    public function test_studio_show_and_graph_routes_are_registered_in_the_configured_middleware_group(): void
    {
        $configuredMiddleware = config('flow-admin.middleware', ['web', 'auth']);
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        foreach (['flow-admin.studio.show', 'flow-admin.studio.graph'] as $routeName) {
            $route = $routes->first(fn ($route) => $route->getName() === $routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must be registered");
            $this->assertSame(
                (array) $configuredMiddleware,
                array_values(array_intersect((array) $configuredMiddleware, $route->middleware())),
                "Route [{$routeName}] must carry every middleware in flow-admin.middleware, same as every other admin page route.",
            );
        }
    }

    public function test_graph_endpoint_returns_the_envelope_for_a_published_flow(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.graph', ['name' => 'OrderCheckoutFlow']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['graph' => ['schema_version', 'nodes', 'connections'], 'catalog']);
        $response->assertJsonCount(4, 'graph.nodes');
        $response->assertJsonCount(3, 'graph.connections');
    }

    public function test_graph_endpoint_returns_404_json_for_an_unpublished_flow(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.graph', ['name' => 'does-not-exist']));

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }
}
