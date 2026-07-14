<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel;
use Padosoft\LaravelFlow\FlowRun;
use Padosoft\LaravelFlow\Graph\GraphDefinition;
use Padosoft\LaravelFlow\Graph\GraphNode;
use Padosoft\LaravelFlowAdmin\Adapters\EloquentReadModel;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

final class EloquentReadModelTest extends TestCase
{
    private const UTC = 'UTC';

    private int $runIndex = 1;

    private int $approvalIndex = 1;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = tempnam(sys_get_temp_dir(), 'lfa-read-model-') . '.sqlite';
        touch($this->databasePath);

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', $this->databasePath);
        DB::purge('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        $this->migrateFlowTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_list_runs_filters_and_paginates(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::UTC));

        $runPending = $this->seedRun([
            'id' => 'run-pending',
            'status' => FlowRun::STATUS_PENDING,
            'definition_name' => 'billing.run:v1',
            'correlation_id' => 'tenant-a',
            'started_at' => $now->sub(new DateInterval('P1D'))->sub(new DateInterval('PT1H')),
            'duration_ms' => 120,
        ]);
        $runSuccess = $this->seedRun([
            'id' => 'run-success',
            'status' => FlowRun::STATUS_SUCCEEDED,
            'definition_name' => 'billing.run:v2',
            'correlation_id' => 'tenant-b',
            'started_at' => $now->sub(new DateInterval('PT2H')),
            'duration_ms' => 220,
        ]);
        $runFailed = $this->seedRun([
            'id' => 'run-failed',
            'status' => FlowRun::STATUS_FAILED,
            'definition_name' => 'orders.process@v3',
            'correlation_id' => 'tenant-c',
            'started_at' => $now->sub(new DateInterval('PT3H')),
            'duration_ms' => 410,
        ]);
        $runAborted = $this->seedRun([
            'id' => 'run-aborted',
            'status' => FlowRun::STATUS_ABORTED,
            'definition_name' => 'orders.process:v3',
            'correlation_id' => 'tenant-search-key',
            'started_at' => $now->sub(new DateInterval('PT4H')),
            'duration_ms' => 510,
        ]);

        $model = $this->makeModel();
        $all = $model->listRuns(null, null, null, 1, 2);
        $this->assertSame(4, $all->total);
        $this->assertCount(2, $all->items);
        $this->assertSame($runSuccess, $all->items[0]->id);
        $this->assertSame($runFailed, $all->items[1]->id);

        $page2 = $model->listRuns(null, null, null, 2, 2);
        $this->assertCount(2, $page2->items);
        $this->assertSame($runAborted, $page2->items[0]->id);
        $this->assertSame($runPending, $page2->items[1]->id);

        $failed = $model->listRuns('failed');
        $this->assertSame(2, $failed->total);
        $this->assertCount(2, $failed->items);

        $flow = $model->listRuns(null, 'billing.run');
        $this->assertSame(2, $flow->total);

        $search = $model->listRuns(null, null, 'search-key');
        $this->assertSame(1, $search->total);
        $this->assertSame('run-aborted', $search->items[0]->id);
    }

    public function test_find_run_includes_steps_and_audit_from_flow_dashboard_read_model(): void
    {
        $runId = $this->seedRun([
            'id' => 'run-detail',
            'status' => FlowRun::STATUS_FAILED,
            'definition_name' => 'checkout:2.1',
            'correlation_id' => 'operator-foo',
            'started_at' => $this->tsMinutesAgo(8),
            'finished_at' => $this->tsMinutesAgo(1),
            'duration_ms' => 800,
        ]);

        $this->seedStep($runId, [
            'id' => 11,
            'sequence' => 1,
            'step_name' => 'validate',
            'status' => 'failed',
            'started_at' => $this->tsMinutesAgo(7),
            'finished_at' => $this->tsMinutesAgo(6),
            'duration_ms' => 2500,
            'error_message' => 'validation error',
        ]);

        $this->seedStep($runId, [
            'id' => 12,
            'sequence' => 2,
            'step_name' => 'charge',
            'status' => 'running',
            'started_at' => $this->tsMinutesAgo(6),
            'duration_ms' => null,
        ]);

        $this->seedAudit([
            'run_id' => $runId,
            'step_name' => 'validate',
            'event' => 'flow.step_failed',
            'occurred_at' => $this->tsMinutesAgo(5),
            'payload' => ['reason' => 'validation'],
        ]);

        $detail = $this->makeModel()->findRun($runId);

        $this->assertNotNull($detail);
        $this->assertSame($runId, $detail->summary->id);
        $this->assertSame('checkout', $detail->summary->flowName);
        $this->assertSame('2.1', $detail->summary->flowVersion);
        $this->assertSame(2, $detail->summary->stepCount);
        $this->assertSame(2, $detail->summary->attemptsTotal);
        $this->assertSame('operator-foo', $detail->summary->actor);
        $this->assertSame('failed', $detail->summary->status);
        $this->assertCount(2, $detail->steps);
        $this->assertSame('validate', $detail->steps[0]->name);
        $this->assertSame('charge', $detail->steps[1]->name);
        $this->assertCount(1, $detail->audit);
        $this->assertSame('validation', $detail->audit[0]->payload['reason']);
    }

    public function test_approvals_filter_query_and_pending_sorting(): void
    {
        $runId = $this->seedRun(['id' => 'run-approvals']);

        $oldest = (new DateTimeImmutable('now', new DateTimeZone(self::UTC)))->sub(new DateInterval('P1D'))->setTime(8, 0);
        $middle = $oldest->add(new DateInterval('PT1H'));
        $newest = $oldest->add(new DateInterval('PT2H'));

        $this->seedApproval([
            'id' => 'approval-1',
            'run_id' => $runId,
            'step_name' => 'validate',
            'status' => 'approved',
            'created_at' => $newest,
        ]);
        $this->seedApproval([
            'id' => 'approval-2',
            'run_id' => $runId,
            'step_name' => 'charge',
            'status' => FlowRun::STATUS_PENDING,
            'created_at' => $middle,
            'actor' => json_encode(['alice']),
        ]);
        $this->seedApproval([
            'id' => 'approval-3',
            'run_id' => $runId,
            'step_name' => 'capture',
            'status' => 'rejected',
            'created_at' => $oldest,
        ]);

        $all = $this->makeModel()->listApprovals(null, null, 1, 25);
        $this->assertSame(3, $all->total);
        $this->assertCount(3, $all->items);
        $this->assertSame('approval-1', $all->items[0]->tokenId);
        $this->assertSame('approval-2', $all->items[1]->tokenId);
        $this->assertSame('approval-3', $all->items[2]->tokenId);

        $granted = $this->makeModel()->listApprovals('granted');
        $this->assertSame(1, $granted->total);
        $this->assertSame('granted', $granted->items[0]->status);

        $query = $this->makeModel()->listApprovals(null, 'charge');
        $this->assertSame(1, $query->total);
        $this->assertSame('charge', $query->items[0]->stepName);

        $pending = $this->makeModel()->pendingApprovals(5);
        $this->assertCount(1, $pending);
        $this->assertSame('approval-2', $pending[0]->tokenId);
        $this->assertSame('alice', $pending[0]->approver);
    }

    public function test_webhook_outbox_filters_pending_and_delivery_statuses(): void
    {
        $runId = $this->seedRun(['id' => 'run-outbox']);

        $this->seedOutbox([
            'id' => 11,
            'run_id' => $runId,
            'event' => 'flow.completed',
            'status' => 'pending',
            'available_at' => $this->tsMinutesAgo(90),
            'attempts' => 1,
            'last_error' => null,
        ]);
        $this->seedOutbox([
            'id' => 12,
            'run_id' => $runId,
            'event' => 'flow.failed',
            'status' => 'delivering',
            'available_at' => $this->tsMinutesAgo(88),
            'attempts' => 2,
            'last_error' => 'temporary dns failure',
        ]);
        $this->seedOutbox([
            'id' => 13,
            'run_id' => $runId,
            'event' => 'flow.paused',
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'fatal',
        ]);

        $pending = $this->makeModel()->pendingWebhookOutbox();
        $this->assertCount(2, $pending);
        $this->assertSame('pending', $pending[0]->status);
        $this->assertSame('pending', $pending[1]->status);

        $pendingFiltered = $this->makeModel()->listWebhookOutbox('pending');
        $this->assertSame(1, $pendingFiltered->total);
        $this->assertSame('flow.completed', $pendingFiltered->items[0]->eventType);

        $search = $this->makeModel()->listWebhookOutbox(null, 'flow.');
        $this->assertSame(3, $search->total);
        $this->assertSame('flow.paused', $search->items[0]->eventType);
        $this->assertSame('flow.failed', $search->items[1]->eventType);
        $this->assertSame('flow.completed', $search->items[2]->eventType);
        $this->assertStringContainsString($runId, $pending[0]->destination);
    }

    public function test_kpi_summary_for_windowed_rates_and_deltas(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::UTC));
        $oneHour = new DateInterval('PT1H');
        $twoHours = new DateInterval('PT2H');
        $twentyFiveHours = new DateInterval('PT25H');

        $this->seedRun([
            'id' => 'run-kpi-current-success',
            'status' => FlowRun::STATUS_SUCCEEDED,
            'started_at' => $now->sub($oneHour),
            'finished_at' => $now->sub($oneHour)->add(new DateInterval('PT20M')),
            'duration_ms' => 400,
        ]);
        $this->seedRun([
            'id' => 'run-kpi-current-failed',
            'status' => FlowRun::STATUS_FAILED,
            'started_at' => $now->sub($twoHours),
            'finished_at' => $now->sub($twoHours)->add(new DateInterval('PT12M')),
            'duration_ms' => 700,
        ]);
        $this->seedRun([
            'id' => 'run-kpi-current-aborted',
            'status' => FlowRun::STATUS_ABORTED,
            'started_at' => $now->sub($oneHour),
            'finished_at' => $now->sub($oneHour)->add(new DateInterval('PT25M')),
            'duration_ms' => 200,
        ]);
        $this->seedRun([
            'id' => 'run-kpi-previous-failed',
            'status' => FlowRun::STATUS_FAILED,
            'started_at' => $now->sub($twentyFiveHours),
            'finished_at' => $now->sub($twentyFiveHours)->add(new DateInterval('PT4M')),
            'duration_ms' => 1000,
        ]);

        $kpis = $this->makeModel()->kpis();

        $this->assertSame(3, $kpis->totalRuns);
        $this->assertSame(2, $kpis->failedRuns);
        $this->assertSame(1, $kpis->deltaFailedRuns);
        $this->assertSame(433, $kpis->avgDurationMs);
        $this->assertSame(700, $kpis->p95DurationMs);
        $this->assertInstanceOf(KpiSummary::class, $kpis);
    }

    public function test_find_run_returns_null_for_unknown_run_id(): void
    {
        $this->assertNull($this->makeModel()->findRun('does-not-exist'));
    }

    public function test_definitions_reports_declared_step_count_from_definition_repository(): void
    {
        // declaredStepCount() must come from the definition's stored graph
        // (3 nodes below), not from a count of step-execution rows — the
        // bug the rewrite fixes. Seed only 2 step rows to prove the two
        // numbers are read from different sources and don't coincide.
        $this->seedRun([
            'id' => 'run-def-checkout',
            'status' => FlowRun::STATUS_SUCCEEDED,
            'definition_name' => 'checkout:1',
        ]);
        $this->seedStep('run-def-checkout', [
            'id' => 21,
            'sequence' => 1,
            'step_name' => 'validate',
            'status' => 'succeeded',
            'started_at' => $this->tsMinutesAgo(5),
        ]);
        $this->seedStep('run-def-checkout', [
            'id' => 22,
            'sequence' => 2,
            'step_name' => 'charge',
            'status' => 'succeeded',
            'started_at' => $this->tsMinutesAgo(4),
        ]);

        $this->app->make(DefinitionRepository::class)->createDraft('checkout', new GraphDefinition([
            new GraphNode('validate', 'test.step'),
            new GraphNode('charge', 'test.step'),
            new GraphNode('notify', 'test.step'),
        ], []));

        $definitions = $this->makeModel()->definitions();

        $checkout = null;
        foreach ($definitions as $definition) {
            if ($definition->name === 'checkout') {
                $checkout = $definition;
            }
        }

        $this->assertNotNull($checkout);
        $this->assertSame(3, $checkout->stepCount);
    }

    public function test_recent_batch_cap_bounds_list_runs_to_most_recent_window(): void
    {
        // Mirrors EloquentReadModel::RECENT_BATCH_CAP. Seed one more run
        // than the cap, each one minute further in the past, so the very
        // last one seeded is the single oldest run and falls outside the
        // bounded "recent" window read by listRuns()/search.
        $cap = 200;
        $now = new DateTimeImmutable('now', new DateTimeZone(self::UTC));

        for ($i = 0; $i < $cap + 1; $i++) {
            $this->seedRun([
                'id' => sprintf('run-cap-%04d', $i),
                'status' => FlowRun::STATUS_SUCCEEDED,
                'correlation_id' => $i === $cap ? 'outside-window-marker' : 'tenant-cap-' . $i,
                'started_at' => $now->sub(new DateInterval(sprintf('PT%dM', $i))),
            ]);
        }

        $model = $this->makeModel();

        $all = $model->listRuns(null, null, null, 1, $cap);
        $this->assertSame($cap, $all->total);

        $outsideWindow = $model->listRuns(null, null, 'outside-window-marker');
        $this->assertSame(0, $outsideWindow->total);
    }

    public function test_kpis_are_not_truncated_by_the_recent_batch_cap_within_the_24h_window(): void
    {
        // runsInWindow() pages through every match instead of stopping at
        // RECENT_BATCH_CAP (200) like recentRuns() intentionally does —
        // seed more than the cap, all inside the rolling 24h KPI window,
        // and assert the full count survives.
        $cap = 200;
        $now = new DateTimeImmutable('now', new DateTimeZone(self::UTC));
        $seeded = $cap + 25;

        for ($i = 0; $i < $seeded; $i++) {
            $this->seedRun([
                'id' => sprintf('run-kpi-cap-%04d', $i),
                'status' => FlowRun::STATUS_SUCCEEDED,
                'started_at' => $now->sub(new DateInterval('PT1H'))->sub(new DateInterval(sprintf('PT%dS', $i))),
                'duration_ms' => 100,
            ]);
        }

        $kpis = $this->makeModel()->kpis();

        $this->assertSame($seeded, $kpis->totalRuns);
    }

    public function test_kpi_window_boundary_does_not_double_count_a_run(): void
    {
        // A run started exactly at the current/previous window boundary
        // must be counted in exactly one of the two windows, not both.
        $now = new DateTimeImmutable('now', new DateTimeZone(self::UTC));
        $windowStart = $now->sub(new DateInterval('P1D'));

        $this->seedRun([
            'id' => 'run-on-boundary',
            'status' => FlowRun::STATUS_SUCCEEDED,
            'started_at' => $windowStart,
            'duration_ms' => 100,
        ]);

        $kpis = $this->makeModel()->kpis();

        // If the boundary run were double-counted, totalRuns would be 2
        // (present in both the current and previous window aggregates).
        $this->assertSame(1, $kpis->totalRuns);
        $this->assertSame(1, $kpis->deltaTotalRuns);
    }

    private function makeModel(): EloquentReadModel
    {
        return new EloquentReadModel(
            $this->app->make(FlowDashboardReadModel::class),
            $this->app->make(DefinitionRepository::class),
        );
    }

    private function migrateFlowTables(): void
    {
        $directory = $this->resolveMigrationDirectory();
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $this->runMigration($file);
        }
    }

    private function resolveMigrationDirectory(): string
    {
        $candidates = [
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor/padosoft/laravel-flow/database/migrations',
            base_path('vendor/padosoft/laravel-flow/database/migrations'),
            dirname(base_path(), 1) . DIRECTORY_SEPARATOR . 'vendor/padosoft/laravel-flow/database/migrations',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        $this->fail('Migration directory not found for padosoft/laravel-flow');

        return '';
    }

    private function seedAudit(array $attributes): void
    {
        DB::table('flow_audit')->insert([
            'id' => $attributes['id'] ?? null,
            'run_id' => $attributes['run_id'],
            'step_name' => $attributes['step_name'] ?? null,
            'event' => $attributes['event'],
            'occurred_at' => $this->asTimestamp($attributes['occurred_at'] ?? null),
            'payload' => json_encode($attributes['payload'] ?? null),
            'business_impact' => json_encode($attributes['business_impact'] ?? null),
            'created_at' => $this->asTimestamp($attributes['created_at'] ?? new DateTimeImmutable('now', new DateTimeZone(self::UTC))),
        ]);
    }

    private function runMigration(string $path): void
    {
        if (! file_exists($path)) {
            $this->fail('Migration file not found: ' . $path);
        }

        $migration = require $path;
        $migration->up();
    }

    private function seedRun(array $attributes): string
    {
        $id = $attributes['id'] ?? $this->nextRunId();
        $startedAt = $attributes['started_at'] ?? $this->tsMinutesAgo(5);

        DB::table('flow_runs')->insert([
            'id' => $id,
            'definition_name' => $attributes['definition_name'] ?? 'billing.sync:v1',
            'status' => $attributes['status'] ?? FlowRun::STATUS_PENDING,
            'dry_run' => 0,
            'input' => json_encode($attributes['input'] ?? null),
            'output' => json_encode($attributes['output'] ?? null),
            'business_impact' => json_encode($attributes['business_impact'] ?? null),
            'failed_step' => $attributes['failed_step'] ?? null,
            'compensated' => $attributes['compensated'] ?? false,
            'compensation_status' => $attributes['compensation_status'] ?? null,
            'correlation_id' => $attributes['correlation_id'] ?? null,
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'started_at' => $this->asTimestamp($startedAt),
            'finished_at' => $this->asTimestamp($attributes['finished_at'] ?? null),
            'duration_ms' => $attributes['duration_ms'] ?? null,
            'created_at' => $this->asTimestamp($startedAt),
            'updated_at' => $this->asTimestamp($startedAt),
        ]);

        return $id;
    }

    private function seedStep(string $runId, array $attributes): void
    {
        // `flow_steps` (v1) was superseded by `flow_run_nodes` (graph
        // executor, Macro C): `step_name` -> `node_id`, `input`/`output` ->
        // `inputs`/`outputs`, plus the required `node_type` column. The
        // attribute key stays `step_name` here purely as the caller-facing
        // parameter name; it maps onto the `node_id` column below.
        DB::table('flow_run_nodes')->insert([
            'id' => $attributes['id'],
            'run_id' => $runId,
            'sequence' => $attributes['sequence'],
            'node_id' => $attributes['step_name'],
            'node_type' => $attributes['node_type'] ?? 'legacy.step',
            'handler' => $attributes['handler'] ?? 'App\\Flow\\Handler',
            'status' => $attributes['status'] ?? 'running',
            'inputs' => json_encode($attributes['input'] ?? []),
            'outputs' => json_encode($attributes['output'] ?? null),
            'business_impact' => json_encode($attributes['business_impact'] ?? null),
            'error_class' => $attributes['error_class'] ?? null,
            'error_message' => $attributes['error_message'] ?? null,
            'dry_run_skipped' => 0,
            'started_at' => $this->asTimestamp($attributes['started_at']),
            'finished_at' => $this->asTimestamp($attributes['finished_at'] ?? null),
            'duration_ms' => $attributes['duration_ms'] ?? null,
            'created_at' => $this->asTimestamp($attributes['started_at']),
            'updated_at' => $this->asTimestamp($attributes['started_at']),
        ]);
    }

    private function seedApproval(array $attributes): void
    {
        $createdAt = $attributes['created_at'] ?? new DateTimeImmutable('now', new DateTimeZone(self::UTC));
        $status = $attributes['status'] ?? FlowRun::STATUS_PENDING;
        $id = $attributes['id'] ?? 'approval-' . ($this->approvalIndex++);

        DB::table('flow_approvals')->insert([
            'id' => $id,
            'run_id' => $attributes['run_id'],
            'step_name' => $attributes['step_name'],
            'status' => $status,
            'token_hash' => hash('sha256', $id),
            'payload' => json_encode($attributes['payload'] ?? ['scope' => 'unit-test']),
            'actor' => $attributes['actor'] ?? null,
            'expires_at' => $this->asTimestamp($attributes['expires_at'] ?? null),
            'consumed_at' => $this->asTimestamp($attributes['consumed_at'] ?? null),
            'decided_at' => $this->asTimestamp($attributes['decided_at'] ?? null),
            'created_at' => $this->asTimestamp($createdAt),
            'updated_at' => $this->asTimestamp($createdAt),
        ]);
    }

    private function seedOutbox(array $attributes): void
    {
        DB::table('flow_webhook_outbox')->insert([
            'id' => $attributes['id'],
            'run_id' => $attributes['run_id'],
            'approval_id' => $attributes['approval_id'] ?? null,
            'event' => $attributes['event'],
            'status' => $attributes['status'],
            'payload' => json_encode($attributes['payload'] ?? null),
            'attempts' => $attributes['attempts'] ?? 0,
            'max_attempts' => 5,
            'available_at' => $this->asTimestamp($attributes['available_at'] ?? null),
            'delivered_at' => $this->asTimestamp($attributes['delivered_at'] ?? null),
            'failed_at' => $this->asTimestamp($attributes['failed_at'] ?? null),
            'last_error' => $attributes['last_error'] ?? null,
            'created_at' => $this->asTimestamp(new DateTimeImmutable('now', new DateTimeZone(self::UTC))),
            'updated_at' => $this->asTimestamp(new DateTimeImmutable('now', new DateTimeZone(self::UTC))),
        ]);
    }

    private function nextRunId(): string
    {
        return sprintf('run-%04d', $this->runIndex++);
    }

    private function tsMinutesAgo(int $minutes): DateTimeImmutable
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::UTC)))->sub(new DateInterval(sprintf('PT%dM', $minutes)));
    }

    private function asTimestamp(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone(self::UTC))->format('Y-m-d H:i:s');
    }
}
