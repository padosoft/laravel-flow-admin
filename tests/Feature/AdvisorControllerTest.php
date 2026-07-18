<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel;
use Padosoft\LaravelFlow\Graph\GraphSerializer;
use Padosoft\LaravelFlow\Node\NodeRegistry;
use Padosoft\LaravelFlowAdmin\Authorizers\AllowAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Tests\Concerns\MigratesFlowTables;
use Padosoft\LaravelFlowAdmin\Tests\Stubs\DemoTriggerNode;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use Padosoft\LaravelFlowAI\Advisor\Analyzer;
use Padosoft\LaravelFlowAI\Advisor\Finding;
use Padosoft\LaravelFlowAI\Advisor\FlowAdvisor;
use RuntimeException;

/**
 * The Flow Advisor inbox scan is a MUTATION (it persists advisor draft
 * versions), so it sits behind the `edit_definition` gate and is exercised
 * here with a canned {@see Analyzer} — the real analyzers derive findings
 * from persisted run history, which this test does not need to reproduce to
 * cover the controller's shaping/gating/error paths.
 */
final class AdvisorControllerTest extends TestCase
{
    use MigratesFlowTables;

    protected function tearDown(): void
    {
        $this->tearDownFlowDatabase();

        parent::tearDown();
    }

    public function test_advisor_routes_are_registered_in_the_configured_middleware_group(): void
    {
        $configuredMiddleware = config('flow-admin.middleware', ['web', 'auth']);
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        foreach (['flow-admin.advisor.index', 'flow-admin.advisor.scan'] as $routeName) {
            $route = $routes->first(fn ($route) => $route->getName() === $routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must be registered");
            $this->assertSame(
                (array) $configuredMiddleware,
                array_values(array_intersect((array) $configuredMiddleware, $route->middleware())),
                "Route [{$routeName}] must carry every middleware in flow-admin.middleware.",
            );
        }
    }

    public function test_advisor_index_page_renders_with_the_scan_affordance(): void
    {
        // index() reads sidebar counts from the read model (flow_* tables).
        $this->setUpFlowDatabase();

        $response = $this->get(route('flow-admin.advisor.index'));

        $response->assertStatus(200);
        // Assert the button's VISIBLE label (rendered only inside the button),
        // not the "advisor-scan-button" testid — that string also appears in
        // the page's inline <script> selectors, so asserting it would pass even
        // if the button weren't rendered. The optional AI package is installed
        // in the test matrix, so the affordance is present.
        $response->assertSee('Scan flows for suggestions');
    }

    public function test_advisor_scan_is_forbidden_by_default(): void
    {
        // Same deny-by-default authoring gate as Studio draft/publish — the
        // scan writes draft versions, so DenyAllAuthorizer must block it.
        $response = $this->postJson(route('flow-admin.advisor.scan'));

        $response->assertStatus(403);
    }

    public function test_advisor_scan_returns_suggestions_and_creates_a_draft_without_publishing(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();

        // Seed one flow so FlowAdvisor::createDraft() has a `latest()` to base
        // its advisor draft on.
        $seededVersion = $this->seedDraft('advisor-flow');
        $definitions = $this->app->make(DefinitionRepository::class);
        $this->assertCount(1, $definitions->versions('advisor-flow'));

        $finding = new Finding('reliability.failure_hotspot', 'Node "charge" fails often', ['failure_rate' => 0.42]);
        $this->bindAdvisorWith(new AdvisorControllerTestCannedAnalyzer($finding), ['advisor-flow']);

        $response = $this->postJson(route('flow-admin.advisor.scan'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('suggestions.0.flow', 'advisor-flow');
        $response->assertJsonPath('suggestions.0.finding.type', 'reliability.failure_hotspot');
        $response->assertJsonPath('suggestions.0.finding.summary', 'Node "charge" fails often');
        $response->assertJsonPath('suggestions.0.finding.rationale.failure_rate', 0.42);

        // The advisor persisted a NEW draft (never published) — version count
        // grew from 1 to 2, and the reported draft version is that new one.
        $versions = $definitions->versions('advisor-flow');
        $this->assertCount(2, $versions);
        $this->assertGreaterThan($seededVersion, (int) $response->json('suggestions.0.draft_version'));
    }

    public function test_advisor_scan_returns_a_sanitized_500_when_the_advisor_throws(): void
    {
        $this->setUpFlowDatabase();
        $this->bindAllowingAuthorizer();
        $this->registerDemoTrigger();
        $this->seedDraft('advisor-flow');

        $this->bindAdvisorWith(new AdvisorControllerTestThrowingAnalyzer, ['advisor-flow']);

        $response = $this->postJson(route('flow-admin.advisor.scan'));

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $this->assertStringNotContainsString('analyzer exploded', (string) $response->getContent());
    }

    /**
     * @param  list<string>  $exposedFlowNames
     */
    private function bindAdvisorWith(Analyzer $analyzer, array $exposedFlowNames): void
    {
        $this->app->instance(FlowAdvisor::class, new FlowAdvisor(
            readModel: $this->app->make(FlowDashboardReadModel::class),
            definitions: $this->app->make(DefinitionRepository::class),
            analyzers: [$analyzer],
            exposedFlowNames: $exposedFlowNames,
        ));
    }

    private function registerDemoTrigger(): void
    {
        $registry = $this->app->make(NodeRegistry::class);
        if (! $registry->has('test.studio.demo-trigger')) {
            $registry->register(DemoTriggerNode::class);
        }
    }

    private function seedDraft(string $name): int
    {
        $graph = $this->app->make(GraphSerializer::class)->fromArray([
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [['id' => 'start', 'type' => 'test.studio.demo-trigger', 'config' => [], 'position' => ['x' => 0, 'y' => 0]]],
            'connections' => [],
        ]);

        return $this->app->make(DefinitionRepository::class)->createDraft($name, $graph)->version;
    }

    private function bindAllowingAuthorizer(): void
    {
        $this->app->bind(ActionAuthorizer::class, AllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);
    }
}

/**
 * @internal test double — returns a fixed finding regardless of run history.
 */
final class AdvisorControllerTestCannedAnalyzer implements Analyzer
{
    public function __construct(private readonly Finding $finding) {}

    public function analyze(string $definitionName, array $runs): array
    {
        return [$this->finding];
    }
}

/**
 * @internal test double — throws to exercise the controller's sanitized 500.
 */
final class AdvisorControllerTestThrowingAnalyzer implements Analyzer
{
    public function analyze(string $definitionName, array $runs): array
    {
        throw new RuntimeException('analyzer exploded with a secret detail');
    }
}
