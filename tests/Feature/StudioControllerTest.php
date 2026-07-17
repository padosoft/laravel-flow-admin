<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Mockery;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Graph\GraphSerializer;
use Padosoft\LaravelFlow\Graph\StoredDefinition;
use Padosoft\LaravelFlow\Node\NodeRegistry;
use Padosoft\LaravelFlowAdmin\Ai\FakeLlmClient;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Tests\Concerns\MigratesFlowTables;
use Padosoft\LaravelFlowAdmin\Tests\Stubs\DemoTriggerNode;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use Padosoft\LaravelFlowAI\Builder\FlowBuilderService;
use Padosoft\LaravelFlowAI\Contracts\LlmClient;
use Padosoft\LaravelFlowAI\Llm\LlmRequest;
use Padosoft\LaravelFlowAI\Llm\LlmResponse;
use RuntimeException;

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

        foreach (['flow-admin.studio.show', 'flow-admin.studio.graph', 'flow-admin.studio.catalog', 'flow-admin.studio.edit', 'flow-admin.studio.edit-graph', 'flow-admin.studio.draft', 'flow-admin.studio.versions', 'flow-admin.studio.version-list', 'flow-admin.studio.diff', 'flow-admin.studio.publish', 'flow-admin.studio.dry-run', 'flow-admin.studio.ai-build'] as $routeName) {
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

    public function test_version_list_returns_a_sanitized_500_when_the_repository_throws(): void
    {
        // A DB outage / DefinitionSignatureException must NOT propagate the
        // raw exception message (which can carry graph contents) to the client.
        $repository = Mockery::mock(DefinitionRepository::class);
        $repository->shouldReceive('versions')->andThrow(new RuntimeException('password=hunter2 in a leaked SQL string'));
        $this->app->instance(DefinitionRepository::class, $repository);

        $response = $this->getJson(route('flow-admin.studio.version-list', ['name' => 'any-flow']));

        $response->assertStatus(500);
        $response->assertDontSee('hunter2');
        $response->assertDontSee('leaked SQL string');
        $response->assertJson(['message' => 'Something went wrong. Try again.']);
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

    public function test_dry_run_returns_the_wave_plan_and_cost_without_writing_any_rows(): void
    {
        $this->setUpFlowDatabase();
        $this->registerDemoTrigger();

        $response = $this->postJson(route('flow-admin.studio.dry-run', ['name' => 'dryrun-flow']), [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [$this->triggerNode('start')],
            'connections' => [],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'flow',
            'plan' => ['waves', 'skipped'],
            'cost' => ['perNode', 'total'],
        ]);
        // Wave 0 (roots) contains the trigger node.
        $this->assertContains('start', $response->json('plan.waves.0'));

        // Dry run must write ZERO rows — no draft version was created.
        $this->assertCount(0, $this->app->make(DefinitionRepository::class)->versions('dryrun-flow'));
    }

    public function test_dry_run_rejects_a_structurally_invalid_graph_with_422(): void
    {
        $response = $this->postJson(route('flow-admin.studio.dry-run', ['name' => 'x']), [
            'schema_version' => 999, // unsupported → GraphSerializer::fromArray throws
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [],
            'connections' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_dry_run_rejects_a_semantically_invalid_graph_with_422(): void
    {
        // A node whose type is not registered parses fine (GraphSerializer /
        // GraphDefinition are structural) but is rejected by GraphValidator —
        // so this exercises dryRun()'s validate() call specifically, the layer
        // GraphDefinition's own constructor does NOT cover.
        $response = $this->postJson(route('flow-admin.studio.dry-run', ['name' => 'x']), [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [
                ['id' => 'a', 'type' => 'does.not.exist', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
            ],
            'connections' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('data.violations.0', 'Unknown node type [does.not.exist] on node [a].');
    }

    public function test_ai_build_endpoint_is_forbidden_by_default(): void
    {
        // Same deny-by-default authoring gate as edit-graph/draft — the
        // AI-builder both reveals graph structure and spends a billable model
        // call, so it must never be reachable under DenyAllAuthorizer.
        $response = $this->postJson(route('flow-admin.studio.ai-build', ['name' => 'any-flow']), [
            'prompt' => 'build me a flow',
        ]);

        $response->assertStatus(403);
    }

    public function test_ai_build_endpoint_generates_a_valid_graph_without_persisting_it(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();
        // The fake model returns a single-node graph using the registered
        // test node type, so FlowBuilderService's GraphValidator pass accepts
        // it and the endpoint returns a real serialized envelope.
        $this->bindBuilderClientReturning('test.studio.demo-trigger');

        $response = $this->postJson(route('flow-admin.studio.ai-build', ['name' => 'ai-flow']), [
            'prompt' => 'When an order is placed, kick off the flow.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('graph.schema_version', 1);
        $response->assertJsonPath('graph.kind', 'laravel-flow');
        $response->assertJsonPath('graph.nodes.0.type', 'test.studio.demo-trigger');

        // The AI-builder proposes; it never writes. No draft version exists.
        $this->assertCount(0, $this->app->make(DefinitionRepository::class)->versions('ai-flow'));
    }

    public function test_ai_build_endpoint_returns_422_when_the_model_produces_an_invalid_graph(): void
    {
        $this->bindAllowingAuthorizer();
        // An UNREGISTERED node type parses structurally but fails core's
        // GraphValidator inside FlowBuilderService::build(), which returns a
        // typed failure the endpoint surfaces as 422 + concrete violations.
        $this->bindBuilderClientReturning('does.not.exist');

        $response = $this->postJson(route('flow-admin.studio.ai-build', ['name' => 'ai-flow']), [
            'prompt' => 'produce something invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('data.violations.0', 'Unknown node type [does.not.exist] on node [ai_generated].');
    }

    public function test_ai_build_endpoint_validates_the_prompt(): void
    {
        $this->bindAllowingAuthorizer();

        $response = $this->postJson(route('flow-admin.studio.ai-build', ['name' => 'ai-flow']), [
            'prompt' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('prompt');
    }

    public function test_ai_build_endpoint_returns_a_sanitized_500_when_the_model_client_throws(): void
    {
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();

        // A real LLM client can throw on transport/API errors (build() only
        // catches PolicyDeniedException). The endpoint must translate that
        // into a sanitized 500, never leak the raw message.
        $this->app->when(FlowBuilderService::class)
            ->needs(LlmClient::class)
            ->give(static fn (): LlmClient => new class implements LlmClient
            {
                public function complete(LlmRequest $request): LlmResponse
                {
                    throw new RuntimeException('provider secret leaked here');
                }
            });

        $response = $this->postJson(route('flow-admin.studio.ai-build', ['name' => 'ai-flow']), [
            'prompt' => 'anything',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $this->assertStringNotContainsString('provider secret', (string) $response->getContent());
    }

    private function bindBuilderClientReturning(string $nodeType): void
    {
        $this->app->when(FlowBuilderService::class)
            ->needs(LlmClient::class)
            ->give(static fn (): FakeLlmClient => new FakeLlmClient($nodeType));
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
