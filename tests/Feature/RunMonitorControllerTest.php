<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use DateTimeImmutable;
use Mockery;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

final class RunMonitorControllerTest extends TestCase
{
    public function test_monitor_routes_are_registered_in_the_configured_middleware_group(): void
    {
        $configuredMiddleware = config('flow-admin.middleware', ['web', 'auth']);
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        foreach (['flow-admin.runs.monitor', 'flow-admin.runs.monitor-state'] as $routeName) {
            $route = $routes->first(fn ($route) => $route->getName() === $routeName);

            $this->assertNotNull($route, "Route [{$routeName}] must be registered");
            $this->assertSame(
                (array) $configuredMiddleware,
                array_values(array_intersect((array) $configuredMiddleware, $route->middleware())),
                "Route [{$routeName}] must carry every middleware in flow-admin.middleware.",
            );
        }
    }

    public function test_monitor_state_returns_node_states_and_aggregate_progress(): void
    {
        $this->useArrayAdapter();
        $id = $this->firstRunId();

        $response = $this->getJson(route('flow-admin.runs.monitor-state', ['id' => $id]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'run_id',
            'status',
            'progress' => ['total', 'completed', 'failed', 'pct'],
            'nodes' => [['node_id', 'state', 'cache_hit', 'sequence']],
        ]);
    }

    public function test_monitor_state_carries_no_run_payloads(): void
    {
        // The frequently-polled state endpoint must expose ONLY node states +
        // progress, never the run's input/output payloads or audit trail.
        $this->useArrayAdapter();
        $id = $this->firstRunId();

        $json = $this->getJson(route('flow-admin.runs.monitor-state', ['id' => $id]))->json();

        $this->assertSame(['run_id', 'status', 'progress', 'nodes'], array_keys($json));
    }

    public function test_monitor_state_returns_404_for_an_unknown_run(): void
    {
        $this->useArrayAdapter();

        $this->getJson(route('flow-admin.runs.monitor-state', ['id' => 'does-not-exist']))->assertStatus(404);
    }

    public function test_monitor_state_reports_cache_hit_and_never_leaks_payloads(): void
    {
        $repository = Mockery::mock(ReadModel::class);
        $repository->shouldReceive('findRun')->andReturn(new RunDetail(
            summary: $this->fakeSummary('run-x', 'succeeded'),
            steps: [
                new Step('fetch', 'succeeded', null, null, null, 1, [], null, false),
                new Step('render', 'succeeded', null, null, null, 1, [], null, true),
            ],
            audit: [],
            inputPayload: ['api_key' => 'sk_live_should_never_appear'],
            outputPayload: [],
        ));
        $this->app->instance(ReadModel::class, $repository);

        $response = $this->getJson(route('flow-admin.runs.monitor-state', ['id' => 'run-x']));

        $response->assertStatus(200);
        $this->assertFalse($response->json('nodes.0.cache_hit'));
        $this->assertTrue($response->json('nodes.1.cache_hit'));
        $response->assertDontSee('sk_live_should_never_appear');

        // Both steps succeeded → progress is 2/2 = 100%.
        $response->assertJsonPath('progress.completed', 2);
        $response->assertJsonPath('progress.total', 2);
        $response->assertJsonPath('progress.pct', 100);
    }

    public function test_monitor_state_normalizes_the_legacy_success_status_to_succeeded(): void
    {
        // The array/demo adapter emits the legacy `success` slug; the monitor
        // must canonicalize it to core's `succeeded` NodeState so colors and
        // the completed counter agree across adapters.
        $repository = Mockery::mock(ReadModel::class);
        $repository->shouldReceive('findRun')->andReturn(new RunDetail(
            summary: $this->fakeSummary('run-y', 'success'),
            steps: [
                new Step('a', 'success', null, null, null, 1, [], null, false),
                new Step('b', 'running', null, null, null, 1, [], null, false),
            ],
            audit: [],
            inputPayload: [],
            outputPayload: [],
        ));
        $this->app->instance(ReadModel::class, $repository);

        $response = $this->getJson(route('flow-admin.runs.monitor-state', ['id' => 'run-y']));

        $response->assertStatus(200);
        $response->assertJsonPath('nodes.0.state', 'succeeded');
        $response->assertJsonPath('nodes.1.state', 'running');
        $response->assertJsonPath('progress.completed', 1);
        $response->assertJsonPath('progress.pct', 50);
    }

    public function test_monitor_page_reflects_the_broadcasting_config(): void
    {
        $this->useArrayAdapter();
        $id = $this->firstRunId();

        $this->app['config']->set('laravel-flow.broadcasting.enabled', false);
        $this->get(route('flow-admin.runs.monitor', ['id' => $id]))
            ->assertStatus(200)
            ->assertSee('data-broadcasting="off"', false)
            ->assertSee('flow-monitor-root', false);

        $this->app['config']->set('laravel-flow.broadcasting.enabled', true);
        $this->get(route('flow-admin.runs.monitor', ['id' => $id]))
            ->assertSee('data-broadcasting="on"', false);
    }

    private function useArrayAdapter(): void
    {
        $this->app['config']->set('flow-admin.adapter', 'array');
        $this->app->forgetInstance(ReadModel::class);
    }

    private function firstRunId(): string
    {
        return $this->app->make(ReadModel::class)->listRuns(perPage: 1)->items[0]->id;
    }

    private function fakeSummary(string $id, string $status): RunSummary
    {
        return new RunSummary(
            id: $id,
            flowName: 'Fake Flow',
            flowVersion: 'v1',
            status: $status,
            actor: 'system',
            correlationId: '',
            startedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            finishedAt: null,
            durationMs: null,
            stepCount: 2,
            attemptsTotal: 2,
        );
    }
}
