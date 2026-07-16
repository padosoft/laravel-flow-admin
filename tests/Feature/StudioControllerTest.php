<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Graph\GraphSerializer;
use Padosoft\LaravelFlow\Graph\StoredDefinition;
use Padosoft\LaravelFlow\Node\NodeRegistry;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Tests\Concerns\MigratesFlowTables;
use Padosoft\LaravelFlowAdmin\Tests\Stubs\DemoTriggerNode;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Pins that the Studio canvas/JSON-API routes are wired into the SAME
 * middleware group as every other admin page — unlike the pre-auth static
 * asset routes (`AssetRouteTest`/`StudioAssetRouteTest`), `graph()` returns
 * flow definition data and must never be reachable outside the configured
 * `flow-admin.middleware` stack. The editor routes (`edit-graph`, `draft`)
 * carry a SECOND gate on top of that — `ActionAuthorizer::canEditDefinition()`,
 * deny-by-default — since they can expose or persist raw node `config`.
 */
final class StudioControllerTest extends TestCase
{
    use MigratesFlowTables;

    /**
     * Guaranteed cleanup even when a test that called setUpFlowDatabase()
     * fails or throws before reaching its own teardown. tearDownFlowDatabase()
     * is a no-op for the tests here that never set up the flow database
     * (guarded on $flowDatabasePath being set).
     */
    protected function tearDown(): void
    {
        $this->tearDownFlowDatabase();

        parent::tearDown();
    }

    public function test_studio_show_and_graph_routes_are_registered_in_the_configured_middleware_group(): void
    {
        $configuredMiddleware = config('flow-admin.middleware', ['web', 'auth']);
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        foreach (['flow-admin.studio.show', 'flow-admin.studio.graph', 'flow-admin.studio.catalog', 'flow-admin.studio.edit', 'flow-admin.studio.edit-graph', 'flow-admin.studio.draft', 'flow-admin.studio.versions', 'flow-admin.studio.version-list', 'flow-admin.studio.diff', 'flow-admin.studio.publish'] as $routeName) {
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

    public function test_edit_page_renders_the_editor_shell(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.edit', ['name' => 'OrderCheckoutFlow']));

        $response->assertStatus(200);
        $response->assertSee('data-testid="flow-studio-root"', false);
        $response->assertSee('data-mode="edit"', false);
    }

    public function test_catalog_endpoint_returns_the_full_catalog_without_authorization_gating(): void
    {
        // No allow-all authorizer bound here on purpose: catalog() carries
        // no secrets (node-type metadata only), so it must stay reachable
        // under the DEFAULT DenyAllAuthorizer, unlike edit-graph/draft.
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.catalog'));

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'catalog');
    }

    public function test_edit_graph_endpoint_is_forbidden_by_default(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.edit-graph', ['name' => 'OrderCheckoutFlow']));

        $response->assertStatus(403);
    }

    public function test_edit_graph_endpoint_returns_unredacted_config_with_an_allowing_authorizer(): void
    {
        $this->bindAllowingAuthorizer();
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.edit-graph', ['name' => 'OrderCheckoutFlow']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['graph' => ['nodes', 'connections'], 'catalog', 'version', 'status']);
        $response->assertJsonPath('graph.nodes.2.config.api_key', 'sk_test_fixture_do_not_leak');
    }

    public function test_edit_graph_endpoint_returns_404_for_a_flow_with_no_version_with_an_allowing_authorizer(): void
    {
        $this->bindAllowingAuthorizer();
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);

        $response = $this->get(route('flow-admin.studio.edit-graph', ['name' => 'does-not-exist']));

        $response->assertStatus(404);
    }

    public function test_store_draft_endpoint_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.studio.draft', ['name' => 'any-flow']), [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [],
            'connections' => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_store_draft_endpoint_rejects_an_invalid_graph_with_422(): void
    {
        $this->bindAllowingAuthorizer();

        $response = $this->postJson(route('flow-admin.studio.draft', ['name' => 'invalid-flow']), [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [
                ['id' => 'a', 'type' => 'does.not.exist', 'config' => [], 'position' => null],
            ],
            'connections' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('data.violations.0', 'Unknown node type [does.not.exist] on node [a].');
    }

    public function test_store_draft_endpoint_saves_a_valid_graph_as_a_new_draft_version(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();

        $registry = $this->app->make(NodeRegistry::class);
        if (! $registry->has('test.studio.demo-trigger')) {
            $registry->register(DemoTriggerNode::class);
        }

        $response = $this->postJson(route('flow-admin.studio.draft', ['name' => 'studio-controller-save-flow']), [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [
                ['id' => 'start', 'type' => 'test.studio.demo-trigger', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
            ],
            'connections' => [],
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true, 'data' => ['version' => 1, 'status' => StoredDefinition::STATUS_DRAFT]]);

        $stored = $this->app->make(DefinitionRepository::class)->latest('studio-controller-save-flow');
        $this->assertNotNull($stored);
        $this->assertSame(StoredDefinition::STATUS_DRAFT, $stored->status);
    }

    public function test_version_list_returns_every_stored_version_newest_first(): void
    {
        $this->setUpFlowDatabase();
        $this->registerDemoTrigger();

        $this->seedDraft('versioned-flow', [$this->triggerNode('start')]);
        $this->seedDraft('versioned-flow', [$this->triggerNode('start'), $this->triggerNode('second')]);

        $response = $this->getJson(route('flow-admin.studio.version-list', ['name' => 'versioned-flow']));

        $response->assertStatus(200);
        $response->assertJsonPath('versions.0.version', 2);
        $response->assertJsonPath('versions.0.status', StoredDefinition::STATUS_DRAFT);
        $response->assertJsonPath('versions.1.version', 1);
    }

    public function test_diff_classifies_added_removed_and_changed_nodes_without_leaking_config(): void
    {
        $this->setUpFlowDatabase();
        $this->registerDemoTrigger();

        // v1: nodes a + b. v2: a with changed config, b removed, c added.
        $this->seedDraft('diff-flow', [
            $this->triggerNode('a'),
            $this->triggerNode('b'),
        ]);
        $this->seedDraft('diff-flow', [
            $this->triggerNode('a', ['secret' => 'sk_live_should_never_appear']),
            $this->triggerNode('c'),
        ]);

        $response = $this->getJson(route('flow-admin.studio.diff', ['name' => 'diff-flow']) . '?from=1&to=2');

        $response->assertStatus(200);
        $response->assertJson(['summary' => ['added' => 1, 'removed' => 1, 'changed' => 1]]);

        $states = collect($response->json('graph.nodes'))->mapWithKeys(fn ($n) => [$n['id'] => $n['diff_state']]);
        $this->assertSame('changed', $states['a']);
        $this->assertSame('removed', $states['b']);
        $this->assertSame('added', $states['c']);

        // Redaction: the changed node's config never leaves the server.
        $response->assertDontSee('sk_live_should_never_appear');
        foreach ($response->json('graph.nodes') as $node) {
            $this->assertArrayNotHasKey('config', $node);
        }
    }

    public function test_diff_requires_both_version_numbers(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();

        $response = $this->getJson(route('flow-admin.studio.diff', ['name' => 'diff-flow']) . '?from=1');

        $response->assertStatus(422);
    }

    public function test_publish_endpoint_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.studio.publish', ['name' => 'any-flow']), ['version' => 1]);

        $response->assertStatus(403);
    }

    public function test_publish_transitions_a_draft_to_published(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();

        $this->seedDraft('publish-flow', [$this->triggerNode('start')]);

        $response = $this->postJson(route('flow-admin.studio.publish', ['name' => 'publish-flow']), ['version' => 1]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'data' => ['version' => 1, 'status' => StoredDefinition::STATUS_PUBLISHED]]);

        $published = $this->app->make(DefinitionRepository::class)->latest('publish-flow', StoredDefinition::STATUS_PUBLISHED);
        $this->assertNotNull($published);
        $this->assertSame(1, $published->version);
    }

    public function test_publish_returns_404_for_an_unknown_version(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();

        $response = $this->postJson(route('flow-admin.studio.publish', ['name' => 'publish-flow']), ['version' => 99]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_publish_returns_409_for_an_already_published_version(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();

        $this->seedDraft('publish-twice', [$this->triggerNode('start')]);
        $this->app->make(DefinitionRepository::class)->publish('publish-twice', 1);

        $response = $this->postJson(route('flow-admin.studio.publish', ['name' => 'publish-twice']), ['version' => 1]);

        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
    }

    private function registerDemoTrigger(): void
    {
        $registry = $this->app->make(NodeRegistry::class);
        if (! $registry->has('test.studio.demo-trigger')) {
            $registry->register(DemoTriggerNode::class);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function triggerNode(string $id, array $config = []): array
    {
        return ['id' => $id, 'type' => 'test.studio.demo-trigger', 'config' => $config, 'position' => ['x' => 0, 'y' => 0]];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $connections
     */
    private function seedDraft(string $name, array $nodes, array $connections = []): int
    {
        $graph = $this->app->make(GraphSerializer::class)->fromArray([
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => $nodes,
            'connections' => $connections,
        ]);

        return $this->app->make(DefinitionRepository::class)->createDraft($name, $graph)->version;
    }

    private function bindAllowingAuthorizer(): void
    {
        $this->app->bind(ActionAuthorizer::class, StudioControllerTestAllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);
    }
}

final class StudioControllerTestAllowAllAuthorizer implements ActionAuthorizer
{
    public function canViewRuns(?array $actor): bool
    {
        return true;
    }

    public function canViewRunDetail(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canReplayRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canApproveByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canRejectByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canCancelRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canRetryWebhook(int $outboxId, ?array $actor): bool
    {
        return true;
    }

    public function canViewKpis(?array $actor): bool
    {
        return true;
    }

    public function canEditDefinition(string $flowName, ?array $actor): bool
    {
        return true;
    }
}
