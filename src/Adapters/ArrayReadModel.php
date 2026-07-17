<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Padosoft\LaravelFlow\Graph\StoredDefinition;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\AuditEvent;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\FlowDefinition;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\OutboxEntry;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ThroughputBucket;
use Padosoft\LaravelFlowAdmin\Contracts\PaginatedResult;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Support\GraphRedactor;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Deterministic fixture-backed read model used by E2E tests and prototype
 * rendering.
 *
 * Source: resources/fixtures/runs.json exported from `.design-source/project/data.jsx`
 * (seed 42, no mutable runtime sources).
 */
final class ArrayReadModel implements ReadModel
{
    /**
     * @param  array<string, mixed>  $fixture
     */
    public function __construct(
        private array $fixture = [],
        private readonly string $path = __DIR__ . '/../../resources/fixtures/runs.json',
    ) {
        $this->fixture = $this->normalizeFixture($this->fixture);
    }

    public function listRuns(?string $status = null, ?string $flow = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $query = trim((string) $query);
        $flow = trim((string) $flow);
        $status = $this->trimOrNull($status);

        $runs = array_values(array_filter(
            $this->runs(),
            function (array $run) use ($status, $flow, $query): bool {
                if ($status !== null && $run['status'] !== $status) {
                    return false;
                }

                if ($flow !== '' && ! str_contains((string) $run['flow_name'], $flow) && ! str_contains((string) $run['flow_def'], $flow)) {
                    return false;
                }

                if ($query !== '') {
                    $needle = strtolower($query);
                    $haystack = strtolower(implode(' ', [
                        (string) $run['id'],
                        (string) $run['flow_name'],
                        (string) $run['flow_def'],
                        (string) $run['correlation_id'],
                    ]));

                    return str_contains($haystack, $needle);
                }

                return true;
            },
        ));

        $total = count($runs);
        $rows = array_slice(
            $this->sortRunsByStartedAt($runs),
            $this->offset($page, $perPage),
            $perPage,
        );

        return new PaginatedResult(
            array_map($this->mapRunSummary(...), $rows),
            $total,
            $page,
            $perPage,
        );
    }

    public function findRun(string $runId): ?RunDetail
    {
        $run = $this->runById($runId);
        if ($run === null) {
            return null;
        }

        return new RunDetail(
            summary: $this->mapRunSummary($run),
            steps: array_values(array_map([$this, 'mapStep'], (array) ($run['steps'] ?? []))),
            audit: array_values(array_map([$this, 'mapAuditEvent'], (array) ($run['audit'] ?? []))),
            inputPayload: is_array($run['payload'] ?? null) ? (($run['payload']['input'] ?? []) ?: []) : [],
            outputPayload: is_array($run['payload'] ?? null) ? (($run['payload']['output'] ?? null) ?? []) : [],
        );
    }

    public function listApprovals(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $query = trim((string) $query);
        $status = $this->normalizedApprovalStatus($status);

        $approvals = [];
        foreach ($this->runs() as $run) {
            $runId = (string) $run['id'];
            $runApprovals = $run['approvals'] ?? [];
            foreach ($runApprovals as $approval) {
                $statusValue = (string) ($approval['status'] ?? 'pending');
                if ($status !== null && $statusValue !== $status) {
                    continue;
                }

                $stepName = (string) ($approval['step'] ?? 'run');
                $tokenId = (string) ($approval['id'] ?? ('approval_' . substr((string) $runId, 0, 8)));
                $row = [
                    'id' => $tokenId,
                    'run_id' => $runId,
                    'step_name' => $stepName,
                    'status' => $statusValue,
                    'step' => $stepName,
                    'token' => (string) ($approval['token'] ?? 'tok_' . substr((string) $runId, 0, 10)),
                    'requested_at' => $approval['requested_at'] ?? null,
                    'decided_at' => $approval['decided_at'] ?? null,
                    'actor' => (string) ($approval['actor'] ?? 'system'),
                    'token_hash' => (string) ($approval['token_hash'] ?? sha1($tokenId)),
                    'description' => (string) ($approval['description'] ?? "Approval requested for {$stepName}"),
                ];

                if ($query !== '') {
                    $needle = strtolower($query);
                    $haystack = strtolower(sprintf('%s %s %s', $row['id'], $row['run_id'], $row['step_name']));
                    if (! str_contains($haystack, $needle)) {
                        continue;
                    }
                }

                $approvals[] = $row;
            }
        }

        $total = count($approvals);
        $rows = array_slice(
            $approvals,
            $this->offset($page, $perPage),
            $perPage,
        );

        return new PaginatedResult(
            array_map($this->mapApproval(...), $rows),
            $total,
            $page,
            $perPage,
        );
    }

    public function pendingApprovals(int $limit = 25): array
    {
        $limit = $this->normalizeLimit($limit);

        $pending = [];
        foreach ($this->runs() as $run) {
            foreach ($run['approvals'] ?? [] as $approval) {
                if (($approval['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $pending[] = $this->mapApproval([
                    'id' => (string) ($approval['id'] ?? ('approval_' . substr((string) $run['id'], 0, 8))),
                    'run_id' => (string) $run['id'],
                    'step_name' => (string) ($approval['step'] ?? 'run'),
                    'status' => 'pending',
                    'requested_at' => $approval['requested_at'] ?? null,
                    'actor' => (string) ($approval['actor'] ?? 'system'),
                    'decided_at' => $approval['decided_at'] ?? null,
                    'description' => (string) ($approval['description'] ?? 'Manual approval required'),
                ]);
            }
        }

        usort($pending, function (ApprovalSummary $a, ApprovalSummary $b): int {
            return $a->requestedAt <=> $b->requestedAt;
        });

        return array_slice($pending, 0, $limit);
    }

    public function listWebhookOutbox(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $query = trim((string) $query);
        $status = $this->trimOrNull($status);

        $rows = [];
        foreach ($this->runs() as $run) {
            foreach ($run['outbox'] ?? [] as $outbox) {
                $statusValue = (string) ($outbox['status'] ?? 'pending');
                if ($status !== null && $statusValue !== $status) {
                    continue;
                }

                $entry = [
                    'id' => (string) ($outbox['id'] ?? ('outbox_' . md5((string) ($outbox['target'] ?? '')))),
                    'event' => (string) ($outbox['topic'] ?? ''),
                    'target' => (string) ($outbox['target'] ?? ''),
                    'status' => $statusValue,
                    'attempts' => (int) ($outbox['attempts'] ?? 0),
                    'next_retry_at' => $outbox['next_retry_at'] ?? null,
                    'last_error' => $outbox['last_response'] ?? null,
                ];

                if ($query !== '') {
                    $needle = strtolower($query);
                    $haystack = strtolower(sprintf('%s %s %s', $entry['id'], $entry['event'], $entry['target']));
                    if (! str_contains($haystack, $needle)) {
                        continue;
                    }
                }

                $rows[] = $entry;
            }
        }

        $total = count($rows);
        $pageRows = array_slice(
            $rows,
            $this->offset($page, $perPage),
            $perPage,
        );

        return new PaginatedResult(
            array_map($this->mapOutbox(...), $pageRows),
            $total,
            $page,
            $perPage,
        );
    }

    public function pendingWebhookOutbox(): array
    {
        $entries = [];
        foreach ($this->runs() as $run) {
            foreach ($run['outbox'] ?? [] as $outbox) {
                $status = (string) ($outbox['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'delivering'], true)) {
                    continue;
                }

                $entries[] = $this->mapOutbox([
                    'id' => (string) ($outbox['id'] ?? ('outbox_' . md5((string) ($outbox['target'] ?? '')))),
                    'event' => (string) ($outbox['topic'] ?? ''),
                    'target' => (string) ($outbox['target'] ?? ''),
                    'status' => $status,
                    'attempts' => (int) ($outbox['attempts'] ?? 0),
                    'next_retry_at' => $outbox['next_retry_at'] ?? null,
                    'last_error' => $outbox['last_response'] ?? null,
                ]);
            }
        }

        return $entries;
    }

    public function kpis(): KpiSummary
    {
        $kpis = $this->fixture['KPIS'];
        if (! is_array($kpis)) {
            return new KpiSummary(
                totalRuns: 0,
                deltaTotalRuns: 0,
                successRate: 0.0,
                deltaSuccessRate: 0.0,
                failedRuns: 0,
                deltaFailedRuns: 0,
                avgDurationMs: 0,
                deltaAvgDurationMs: 0,
                p95DurationMs: 0,
            );
        }

        return new KpiSummary(
            totalRuns: (int) ($kpis['runs_24h'] ?? 0),
            deltaTotalRuns: (int) ($kpis['delta_runs_24h'] ?? 0),
            successRate: (float) (($kpis['success_rate'] ?? 0) / 100),
            deltaSuccessRate: (float) (($kpis['delta_success_rate'] ?? 0) / 100),
            failedRuns: (int) ($kpis['failed_24h'] ?? 0),
            deltaFailedRuns: (int) ($kpis['delta_failed_24h'] ?? 0),
            avgDurationMs: (int) ($kpis['avg_duration_ms'] ?? 0),
            deltaAvgDurationMs: (int) ($kpis['delta_avg_duration_ms'] ?? 0),
            p95DurationMs: (int) ($kpis['p95_duration_ms'] ?? 0),
        );
    }

    /**
     * @return list<FlowDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        $runs = $this->runs();
        $byDefinition = [];

        foreach ($runs as $run) {
            $flowId = (string) ($run['flow_def'] ?? '');
            $flowName = (string) ($run['flow_name'] ?? $flowId);
            $version = (string) ($run['version'] ?? 'v1.0');
            $status = (string) ($run['status'] ?? 'pending');

            if (! array_key_exists($flowId, $byDefinition)) {
                $byDefinition[$flowId] = [
                    'name' => $flowName,
                    'version' => $version,
                    'total' => 0,
                    'success' => 0,
                ];
            }

            $byDefinition[$flowId]['total']++;
            if ($status === 'success') {
                $byDefinition[$flowId]['success']++;
            }
        }

        if (! empty($this->fixture['FLOW_DEFS'])) {
            foreach ((array) $this->fixture['FLOW_DEFS'] as $definition) {
                if (! is_array($definition) || ! isset($definition['id'])) {
                    continue;
                }

                $flowId = (string) $definition['id'];
                if (! array_key_exists($flowId, $byDefinition)) {
                    $byDefinition[$flowId] = [
                        'name' => (string) ($definition['name'] ?? $flowId),
                        'version' => (string) ($definition['version'] ?? 'v1.0'),
                        'total' => 0,
                        'success' => 0,
                    ];
                }

                $definitions[$flowId] = new FlowDefinition(
                    name: $byDefinition[$flowId]['name'],
                    version: $byDefinition[$flowId]['version'],
                    stepCount: (int) ($definition['steps'] ?? 0),
                    totalRuns: (int) $byDefinition[$flowId]['total'],
                    successRate: $byDefinition[$flowId]['total'] > 0
                        ? $byDefinition[$flowId]['success'] / $byDefinition[$flowId]['total']
                        : 0.0,
                );
            }
        } else {
            foreach ($byDefinition as $flowId => $definition) {
                $definitions[$flowId] = new FlowDefinition(
                    name: (string) $definition['name'],
                    version: (string) $definition['version'],
                    stepCount: (int) 0,
                    totalRuns: (int) $definition['total'],
                    successRate: $definition['success'] / $definition['total'],
                );
            }
        }

        return array_values($definitions);
    }

    /**
     * Deterministic fixture graph (seed 42, matches the rest of this
     * adapter) for `order_checkout_flow` — the same id `FLOW_DEFS` already
     * uses. Exercises 3 distinct `PortType`s (json/bool/text) across its
     * 3 wires so Playwright/screenshots can assert wire-color-per-type
     * without a real published definition or `NodeRegistry`.
     *
     * Matched by EITHER `FLOW_DEFS`' internal `id` ("order_checkout_flow")
     * OR its human-readable `name` ("OrderCheckoutFlow") — `definitions()`
     * (unchanged, pre-existing behaviour) exposes the pretty `name` as
     * `FlowDefinition::$name`, the same value the Studio index page links
     * with, so this must accept it too, not just the internal id.
     */
    public function graph(string $name): ?array
    {
        if (! $this->matchesFixtureFlow($name, 'order_checkout_flow')) {
            return null;
        }

        return [
            'graph' => GraphRedactor::stripNodeConfig($this->fixtureGraphEnvelope()),
            'catalog' => $this->fixtureCatalog(),
        ];
    }

    public function editableGraph(string $name): ?array
    {
        if (! $this->matchesFixtureFlow($name, 'order_checkout_flow')) {
            return null;
        }

        return [
            'graph' => $this->fixtureGraphEnvelope(),
            'catalog' => $this->fixtureCatalog(),
            'version' => 1,
            'status' => StoredDefinition::STATUS_PUBLISHED,
        ];
    }

    public function catalog(): array
    {
        return $this->fixtureCatalog();
    }

    /**
     * @return array<string, mixed> a `GraphSerializer::toArray()`-shaped envelope
     */
    private function fixtureGraphEnvelope(): array
    {
        return [
            'schema_version' => 1,
            'kind' => 'laravel-flow',
            'metadata' => [],
            'nodes' => [
                ['id' => 'start', 'type' => 'demo.trigger', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'validate', 'type' => 'demo.validate', 'config' => [], 'position' => ['x' => 260, 'y' => 0]],
                // Non-empty config here, on purpose: it is the fixture that
                // proves GraphRedactor::stripNodeConfig() actually strips a
                // secret-shaped key before graph() (but not editableGraph())
                // returns it.
                ['id' => 'charge', 'type' => 'demo.charge', 'config' => ['api_key' => 'sk_test_fixture_do_not_leak'], 'position' => ['x' => 520, 'y' => 0]],
                ['id' => 'notify', 'type' => 'demo.notify', 'config' => [], 'position' => ['x' => 780, 'y' => 0]],
            ],
            'connections' => [
                ['sourceNodeId' => 'start', 'sourcePortKey' => 'out', 'targetNodeId' => 'validate', 'targetPortKey' => 'in'],
                ['sourceNodeId' => 'validate', 'sourcePortKey' => 'valid', 'targetNodeId' => 'charge', 'targetPortKey' => 'authorized'],
                ['sourceNodeId' => 'charge', 'sourcePortKey' => 'receipt', 'targetNodeId' => 'notify', 'targetPortKey' => 'message'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fixtureCatalog(): array
    {
        return [
            'demo.trigger' => [
                'type' => 'demo.trigger',
                'name' => 'Order Received',
                'category' => 'trigger',
                'icon' => 'play',
                'description' => 'Starts the checkout flow.',
                'inputs' => [],
                'outputs' => [
                    ['key' => 'out', 'type' => 'json', 'required' => false, 'label' => 'Order payload', 'multiple' => false],
                ],
            ],
            'demo.validate' => [
                'type' => 'demo.validate',
                'name' => 'Validate Order',
                'category' => 'logic',
                'icon' => 'check',
                'description' => 'Validates the order payload.',
                'inputs' => [
                    ['key' => 'in', 'type' => 'json', 'required' => true, 'label' => 'Order payload', 'multiple' => false],
                ],
                'outputs' => [
                    ['key' => 'valid', 'type' => 'bool', 'required' => false, 'label' => 'Is valid', 'multiple' => false],
                ],
            ],
            'demo.charge' => [
                'type' => 'demo.charge',
                'name' => 'Charge Payment',
                'category' => 'payment',
                'icon' => 'send',
                'description' => 'Charges the customer.',
                'inputs' => [
                    ['key' => 'authorized', 'type' => 'bool', 'required' => true, 'label' => 'Authorized', 'multiple' => false],
                ],
                'outputs' => [
                    ['key' => 'receipt', 'type' => 'text', 'required' => false, 'label' => 'Receipt id', 'multiple' => false],
                ],
            ],
            'demo.notify' => [
                'type' => 'demo.notify',
                'name' => 'Notify Customer',
                'category' => 'notification',
                'icon' => 'bell',
                'description' => 'Sends a confirmation.',
                'inputs' => [
                    ['key' => 'message', 'type' => 'text', 'required' => true, 'label' => 'Message', 'multiple' => false],
                ],
                'outputs' => [],
            ],
        ];
    }

    private function matchesFixtureFlow(string $name, string $id): bool
    {
        foreach ((array) ($this->fixture['FLOW_DEFS'] ?? []) as $definition) {
            if (! is_array($definition) || ($definition['id'] ?? null) !== $id) {
                continue;
            }

            return $name === $id || $name === (string) ($definition['name'] ?? $id);
        }

        return $name === $id;
    }

    /**
     * @return list<ThroughputBucket>
     */
    public function throughputBuckets(): array
    {
        return array_values(array_map($this->mapThroughput(...), (array) ($this->fixture['HOURLY'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function normalizeFixture(array $fixture): array
    {
        $defaults = [
            'FLOW_DEFS' => [],
            'RUNS' => [],
            'HOURLY' => [],
            'KPIS' => [],
        ];

        $diskFixture = null;
        if ($this->path !== '') {
            $diskFixture = $this->loadFixtureFromDisk();
        }

        if (is_array($diskFixture)) {
            return array_replace($defaults, $diskFixture, $fixture);
        }

        return array_replace($defaults, $fixture);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFixtureFromDisk(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new HttpException(500, 'Unable to read array fixture file');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runs(): array
    {
        $rows = $this->fixture['RUNS'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runById(string $runId): ?array
    {
        foreach ($this->runs() as $run) {
            if ((string) ($run['id'] ?? '') === $runId) {
                return $run;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @return list<array<string, mixed>>
     */
    private function sortRunsByStartedAt(array $runs): array
    {
        usort($runs, function (array $a, array $b): int {
            return $this->compareStartedAt($b, $a);
        });

        return $runs;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareStartedAt(array $a, array $b): int
    {
        $left = (string) ($a['started_at'] ?? '');
        $right = (string) ($b['started_at'] ?? '');

        if ($left === $right) {
            return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
        }

        if ($left < $right) {
            return -1;
        }

        if ($left > $right) {
            return 1;
        }

        return 0;
    }

    private function normalizePage(int $page): int
    {
        return $page > 0 ? $page : 1;
    }

    private function normalizePerPage(int $perPage): int
    {
        return match (true) {
            $perPage > 200 => 200,
            $perPage > 0 => $perPage,
            default => 25,
        };
    }

    private function normalizeLimit(int $limit): int
    {
        return $limit > 0 ? min(200, $limit) : 25;
    }

    private function offset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }

    private function trimOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function mapRunSummary(array $run): RunSummary
    {
        $status = (string) ($run['status'] ?? 'pending');
        $attempts = (int) ($run['retries'] ?? 0);
        $stepTotal = (int) ($run['steps_total'] ?? count($run['steps'] ?? []));

        return new RunSummary(
            id: (string) $run['id'],
            flowName: (string) ($run['flow_name'] ?? (string) $run['flow_def']),
            flowVersion: (string) ($run['version'] ?? 'v1.0'),
            status: $status,
            actor: (string) ($run['actor'] ?? 'system'),
            correlationId: (string) ($run['correlation_id'] ?? ''),
            startedAt: $this->timestampToDateTimeImmutable($run['started_at'] ?? null),
            finishedAt: $this->timestampToDateTimeImmutable($run['finished_at'] ?? null),
            durationMs: is_numeric($run['duration_ms'] ?? null) ? (int) $run['duration_ms'] : null,
            stepCount: $stepTotal,
            attemptsTotal: $attempts + $stepTotal,
        );
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function mapStep(array $step): Step
    {
        $errorMessage = $step['error']['message'] ?? null;
        if (! is_string($errorMessage)) {
            $errorMessage = null;
        }

        return new Step(
            name: (string) ($step['name'] ?? 'step'),
            status: (string) ($step['status'] ?? 'pending'),
            startedAt: $this->timestampToDateTimeImmutable($step['started_at'] ?? null),
            finishedAt: $this->timestampToDateTimeImmutable($step['finished_at'] ?? null),
            durationMs: is_numeric($step['duration_ms'] ?? null) ? (int) $step['duration_ms'] : null,
            attempts: (int) ($step['attempts'] ?? 1),
            dependsOn: array_values(array_filter(
                (array) ($step['depends_on'] ?? []),
                static fn (mixed $value): bool => is_string($value),
            )),
            errorMessage: $errorMessage,
            cacheHit: (bool) ($step['cache_hit'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $approval
     */
    private function mapApproval(array $approval): ApprovalSummary
    {
        return new ApprovalSummary(
            tokenId: (string) ($approval['id'] ?? ''),
            runId: (string) ($approval['run_id'] ?? ''),
            stepName: (string) ($approval['step_name'] ?? 'run'),
            description: (string) ($approval['description'] ?? ''),
            status: (string) ($approval['status'] ?? 'pending'),
            requestedAt: $this->timestampToDateTimeImmutable($approval['requested_at'] ?? null),
            approver: $approval['actor'] ?? null,
            decidedAt: $this->timestampToDateTimeImmutable($approval['decided_at'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $outbox
     */
    private function mapOutbox(array $outbox): OutboxEntry
    {
        return new OutboxEntry(
            id: (string) $outbox['id'],
            eventType: (string) $outbox['event'],
            destination: (string) $outbox['target'],
            status: (string) $outbox['status'],
            attempts: (int) $outbox['attempts'],
            nextAttemptAt: $this->timestampToDateTimeImmutable($outbox['next_retry_at'] ?? null),
            lastError: $outbox['last_error'] === null ? null : (string) $outbox['last_error'],
        );
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    private function mapAuditEvent(array $audit): AuditEvent
    {
        return new AuditEvent(
            at: $this->timestampToDateTimeImmutable($audit['ts'] ?? null),
            type: (string) ($audit['event'] ?? ''),
            actor: (string) ($audit['actor'] ?? 'system'),
            payload: is_array($audit['detail'] ?? null) ? $audit['detail'] : ['detail' => $audit['detail']],
        );
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    private function mapThroughput(array $bucket): ThroughputBucket
    {
        $hour = (string) ($bucket['label'] ?? '00:00');
        $base = DateTimeImmutable::createFromFormat('H:i', $hour, new DateTimeZone('UTC'));
        if ($base === false) {
            return new ThroughputBucket(
                at: new DateTimeImmutable('1970-01-01T00:00:00Z'),
                successCount: (int) ($bucket['success'] ?? 0),
                failedCount: (int) ($bucket['failed'] ?? 0),
            );
        }

        return new ThroughputBucket(
            at: $base->setDate(2026, 5, 6),
            successCount: (int) ($bucket['success'] ?? 0),
            failedCount: (int) ($bucket['failed'] ?? 0),
        );
    }

    private function normalizedApprovalStatus(?string $status): ?string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return null;
        }

        return match ($status) {
            'granted' => 'approved',
            'requested', 'pending', 'approved', 'rejected', 'expired' => $status,
            default => 'pending',
        };
    }

    private function timestampToDateTimeImmutable(mixed $value): DateTimeImmutable
    {
        if ($value === null) {
            return new DateTimeImmutable;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value) || is_float($value)) {
            $seconds = (int) floor(((float) $value) / 1000);

            return (new DateTimeImmutable("@{$seconds}"))->setTimezone(new DateTimeZone('UTC'));
        }

        if (is_string($value) && $value !== '') {
            $date = new DateTimeImmutable($value);

            return $date;
        }

        return new DateTimeImmutable;
    }
}
