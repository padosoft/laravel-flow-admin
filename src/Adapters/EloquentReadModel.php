<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Adapters;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel;
use Padosoft\LaravelFlow\Dashboard\RunDetail as DashboardRunDetail;
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

final readonly class EloquentReadModel implements ReadModel
{
    private const STATUS_PENDING = 'pending';

    private const STATUS_SUCCEEDED = 'succeeded';

    private const STATUS_FAILED = 'failed';

    private const STATUS_ABORTED = 'aborted';

    public function __construct(
        private FlowDashboardReadModel $reader,
    ) {}

    public function listRuns(?string $status = null, ?string $flow = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $engineStatuses = $this->toEngineStatuses($status);
        $search = trim((string) $query);
        $flowFilter = trim((string) $flow);

        $rows = $this->runQuery()
            ->when($engineStatuses !== null, function ($builder) use ($engineStatuses): void {
                if (is_array($engineStatuses)) {
                    $builder->whereIn('status', $engineStatuses);

                    return;
                }

                $builder->where('status', $engineStatuses);
            })
            ->when($flowFilter !== '', function ($builder) use ($flowFilter): void {
                if (str_contains($flowFilter, ':') || str_contains($flowFilter, '@')) {
                    $builder->where('definition_name', $flowFilter);

                    return;
                }

                $builder->where(
                    function ($builder) use ($flowFilter): void {
                        $builder
                            ->where('definition_name', 'like', $this->flowDefinitionLike($flowFilter . ':'))
                            ->orWhere('definition_name', 'like', $this->flowDefinitionLike($flowFilter . '@'));
                    },
                );
            })
            ->when(
                $search !== '',
                fn ($builder) => $builder->where(function ($builder) use ($search): void {
                    $builder
                        ->where('id', 'like', $this->likePattern($search))
                        ->orWhere('definition_name', 'like', $this->likePattern($search))
                        ->orWhere('correlation_id', 'like', $this->likePattern($search));
                }),
            );

        $total = (clone $rows)->count('id');

        /** @var list<array<string, mixed>> $items */
        $items = $rows
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->offset($this->offset($page, $perPage))
            ->limit($perPage)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        $runIds = array_map(
            static fn (string|int $id): string => (string) $id,
            array_values(array_filter(array_column($items, 'id'), static fn (mixed $id): bool => is_string($id) || is_int($id))),
        );

        $runStepCounts = $this->stepCountsByRunId($runIds);

        $mapped = [];
        foreach ($items as $row) {
            $runId = (string) $this->rowValue($row, 'id', '');
            $mapped[] = $this->mapRunSummary($row, $runStepCounts[$runId] ?? 0);
        }

        return new PaginatedResult($mapped, $total, $page, $perPage);
    }

    public function findRun(string $runId): ?RunDetail
    {
        $run = $this->runRowForId($runId);
        if ($run === null) {
            return null;
        }

        $detail = $this->reader->findRun($runId);
        $stepCount = $this->runStepCount($runId);

        if (! ($detail instanceof DashboardRunDetail)) {
            return new RunDetail(
                summary: $this->mapRunSummary($run, $stepCount),
                steps: [],
                audit: [],
                inputPayload: [],
                outputPayload: [],
            );
        }

        $steps = [];
        foreach ($detail->steps as $step) {
            $steps[] = $this->mapStep($step);
        }

        $audit = [];
        foreach ($detail->audit as $entry) {
            $audit[] = $this->mapAuditEvent($entry);
        }

        return new RunDetail(
            summary: $this->mapRunSummary($run, $stepCount, $stepCount),
            steps: $steps,
            audit: $audit,
            inputPayload: is_array($detail->input) ? $detail->input : [],
            outputPayload: is_array($detail->output) ? $detail->output : [],
        );
    }

    public function listApprovals(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $search = trim((string) $query);
        $status = $this->toApprovalStatus($status);

        $rows = DB::table('flow_approvals')
            ->when($status !== null, fn ($builder) => $builder->where('status', $status))
            ->when(
                $search !== '',
                fn ($builder) => $builder->where(function ($builder) use ($search): void {
                    $builder
                        ->where('id', 'like', $this->likePattern($search))
                        ->orWhere('run_id', 'like', $this->likePattern($search))
                        ->orWhere('step_name', 'like', $this->likePattern($search));
                }),
            );

        $total = (clone $rows)->count('id');
        /** @var list<array<string, mixed>> $items */
        $items = $rows
            ->orderByDesc('created_at')
            ->offset($this->offset($page, $perPage))
            ->limit($perPage)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return new PaginatedResult(
            items: array_map($this->mapApproval(...), $items),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function pendingApprovals(int $limit = 25): array
    {
        $limit = $this->normalizeLimit($limit);

        /** @var list<array<string, mixed>> $rows */
        $rows = DB::table('flow_approvals')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return array_map($this->mapApproval(...), $rows);
    }

    public function listWebhookOutbox(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $search = trim((string) $query);
        $status = $this->toWebhookStatus($status);

        $rows = DB::table('flow_webhook_outbox')
            ->when($status !== null, fn ($builder) => $builder->where('status', $status))
            ->when(
                $search !== '',
                fn ($builder) => $builder->where(function ($builder) use ($search): void {
                    $builder
                        ->where('run_id', 'like', $this->likePattern($search))
                        ->orWhere('event', 'like', $this->likePattern($search));
                }),
            );

        $total = (clone $rows)->count('id');
        /** @var list<array<string, mixed>> $items */
        $items = $rows
            ->orderByDesc('id')
            ->offset($this->offset($page, $perPage))
            ->limit($perPage)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return new PaginatedResult(
            items: array_map($this->mapOutbox(...), $items),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function pendingWebhookOutbox(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = DB::table('flow_webhook_outbox')
            ->whereIn('status', [self::STATUS_PENDING, 'delivering'])
            ->orderByDesc('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return array_map($this->mapOutbox(...), $rows);
    }

    public function kpis(): KpiSummary
    {
        $windowNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $windowStart = $windowNow->sub(new DateInterval('P1D'));
        $windowEnd = $windowNow;
        $prevWindowStart = $windowStart->sub(new DateInterval('P1D'));

        $window = $this->runWindowRates($windowStart, $windowEnd);
        $previous = $this->runWindowRates($prevWindowStart, $windowStart);
        $duration = $this->durationStatsForWindow($windowStart, $windowEnd);
        $previousDuration = $this->durationStatsForWindow($prevWindowStart, $windowStart);

        $windowRate = $this->ratio($window['success'], $window['total']);
        $previousRate = $this->ratio($previous['success'], $previous['total']);

        return new KpiSummary(
            totalRuns: $window['total'],
            deltaTotalRuns: $window['total'] - $previous['total'],
            successRate: $windowRate,
            deltaSuccessRate: $windowRate - $previousRate,
            failedRuns: $window['failed'],
            deltaFailedRuns: $window['failed'] - $previous['failed'],
            avgDurationMs: $duration['avg'],
            deltaAvgDurationMs: $duration['avg'] - $previousDuration['avg'],
            p95DurationMs: $duration['p95'],
        );
    }

    /**
     * @return list<ThroughputBucket>
     */
    public function throughputBuckets(): array
    {
        /** @var list<object{bucket:string,success_count:int,failed_count:int}> $rows */
        $rows = DB::table('flow_runs')
            ->selectRaw("strftime('%Y-%m-%d %H:00:00', started_at) as bucket")
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success_count', [self::STATUS_SUCCEEDED])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed_count', [self::STATUS_FAILED, self::STATUS_ABORTED])
            ->whereNotNull('started_at')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->all();

        $result = [];
        foreach ($rows as $row) {
            $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->bucket, new DateTimeZone('UTC'));
            if ($at === false) {
                continue;
            }

            $result[] = new ThroughputBucket(
                at: $at,
                successCount: (int) $row->success_count,
                failedCount: (int) $row->failed_count,
            );
        }

        return $result;
    }

    /**
     * @return list<FlowDefinition>
     */
    public function definitions(): array
    {
        /** @var list<object{definition_name:string,total_runs:int,success_runs:int,step_count:int}> $rows */
        $rows = DB::table('flow_runs')
            ->leftJoin('flow_steps', 'flow_steps.run_id', '=', 'flow_runs.id')
            ->selectRaw('flow_runs.definition_name as definition_name')
            ->selectRaw('COUNT(DISTINCT flow_runs.id) as total_runs')
            ->selectRaw('COUNT(DISTINCT CASE WHEN flow_runs.status = ? THEN flow_runs.id END) as success_runs', [self::STATUS_SUCCEEDED])
            ->selectRaw('COUNT(flow_steps.id) as step_count')
            ->groupBy('flow_runs.definition_name')
            ->orderBy('flow_runs.definition_name')
            ->get()
            ->all();

        $definitions = [];
        foreach ($rows as $row) {
            [$name, $version] = $this->splitFlowDefinition((string) $row->definition_name);
            $totalRuns = (int) $row->total_runs;
            $successRuns = (int) $row->success_runs;

            $definitions[] = new FlowDefinition(
                name: $name,
                version: $version,
                stepCount: (int) $row->step_count,
                totalRuns: $totalRuns,
                successRate: $totalRuns > 0 ? $successRuns / $totalRuns : 0.0,
            );
        }

        return $definitions;
    }

    /**
     * @return array{total:int, failed:int, success:int}
     */
    private function runWindowRates(DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd): array
    {
        $rows = DB::table('flow_runs')
            ->where('started_at', '>=', $this->dbTimestamp($windowStart))
            ->where('started_at', '<', $this->dbTimestamp($windowEnd));

        $total = (clone $rows)->count('id');
        $failed = (clone $rows)
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_ABORTED])
            ->count('id');
        $success = (clone $rows)
            ->where('status', self::STATUS_SUCCEEDED)
            ->count('id');

        return [
            'total' => (int) $total,
            'failed' => (int) $failed,
            'success' => (int) $success,
        ];
    }

    private function runQuery(): Builder
    {
        return DB::table('flow_runs')->select([
            'id',
            'definition_name as definition_name',
            'status',
            'correlation_id',
            'started_at',
            'finished_at',
            'duration_ms',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runRowForId(string $runId): ?array
    {
        $row = $this->runQuery()->where('id', $runId)->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function mapRunSummary(array $run, int $stepCount = 0, ?int $attempts = null): RunSummary
    {
        /** @var array{0: string, 1: string} $parts */
        $parts = $this->splitFlowDefinition((string) $this->rowValue($run, 'definition_name', 'unknown'));

        $correlationId = (string) $this->rowValue($run, 'correlation_id', '');
        $actor = $correlationId !== '' ? $correlationId : 'system';

        return new RunSummary(
            id: (string) $this->rowValue($run, 'id', ''),
            flowName: $parts[0],
            flowVersion: $parts[1],
            status: $this->toPublicStatus((string) $this->rowValue($run, 'status', self::STATUS_PENDING)),
            actor: $actor,
            correlationId: $correlationId,
            startedAt: $this->immutableDate($this->rowValue($run, 'started_at')) ?? new DateTimeImmutable,
            finishedAt: $this->immutableDate($this->rowValue($run, 'finished_at')),
            durationMs: $this->rowValue($run, 'duration_ms') === null ? null : (int) $this->rowValue($run, 'duration_ms'),
            stepCount: $stepCount,
            attemptsTotal: $attempts ?? $stepCount,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapApproval(array $row): ApprovalSummary
    {
        $requestedAt = $this->immutableDate($this->rowValue($row, 'created_at'));
        if ($requestedAt === null) {
            $requestedAt = new DateTimeImmutable;
        }

        return new ApprovalSummary(
            tokenId: (string) $this->rowValue($row, 'id', ''),
            runId: (string) $this->rowValue($row, 'run_id', ''),
            stepName: (string) $this->rowValue($row, 'step_name', ''),
            description: sprintf(
                'Approval requested for %s',
                (string) (($this->rowValue($row, 'step_name', '') !== '' ? $this->rowValue($row, 'step_name') : 'run')),
            ),
            status: $this->toPublicApprovalStatus((string) $this->rowValue($row, 'status', 'pending')),
            requestedAt: $requestedAt,
            approver: $this->actorFromJson($this->rowValue($row, 'actor')),
            decidedAt: $this->immutableDate($this->rowValue($row, 'decided_at')),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapOutbox(array $row): OutboxEntry
    {
        $runId = $this->rowValue($row, 'run_id');
        $runIdString = $runId === null ? null : (string) $runId;
        $destination = $runIdString === null || $runIdString === ''
            ? (string) $this->rowValue($row, 'event', '')
            : 'run:' . $runIdString;

        return new OutboxEntry(
            id: (string) $this->rowValue($row, 'id', ''),
            eventType: (string) $this->rowValue($row, 'event', ''),
            destination: $destination,
            status: $this->toPublicWebhookStatus((string) $this->rowValue($row, 'status', 'pending')),
            attempts: (int) $this->rowValue($row, 'attempts', 0),
            nextAttemptAt: $this->immutableDate($this->rowValue($row, 'available_at')),
            lastError: $this->rowValue($row, 'last_error') === null ? null : (string) $this->rowValue($row, 'last_error'),
        );
    }

    /**
     * @param  array<string, mixed>|object  $step
     */
    private function mapStep(array|object $step): Step
    {
        $durationMs = $this->rowValue($step, 'durationMs') ?? $this->rowValue($step, 'duration_ms');

        return new Step(
            name: (string) ($this->rowValue($step, 'name') ?? $this->rowValue($step, 'step_name', '')),
            status: (string) $this->rowValue($step, 'status', ''),
            startedAt: $this->immutableDate($this->rowValue($step, 'startedAt') ?? $this->rowValue($step, 'started_at')),
            finishedAt: $this->immutableDate($this->rowValue($step, 'finishedAt') ?? $this->rowValue($step, 'finished_at')),
            durationMs: $durationMs === null ? null : (int) $durationMs,
            attempts: 1,
            dependsOn: [],
            errorMessage: $this->rowValue($step, 'errorMessage') ?? $this->rowValue($step, 'error_message'),
        );
    }

    /**
     * @param  array<string, mixed>|object  $event
     */
    private function mapAuditEvent(array|object $event): AuditEvent
    {
        return new AuditEvent(
            at: $this->immutableDate($this->rowValue($event, 'occurredAt') ?? $this->rowValue($event, 'occurred_at')) ?? new DateTimeImmutable,
            type: (string) $this->rowValue($event, 'event', ''),
            actor: (string) $this->rowValue($event, 'actor', 'system'),
            payload: $this->safePayload($this->rowValue($event, 'payload')),
        );
    }

    /**
     * @return array<int, string>|string|null
     */
    private function toEngineStatuses(?string $status): array|string|null
    {
        if ($status === null || $status === '') {
            return null;
        }

        return match ($status) {
            'success' => self::STATUS_SUCCEEDED,
            'failed' => [self::STATUS_FAILED, self::STATUS_ABORTED],
            default => $status,
        };
    }

    private function toPublicStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_SUCCEEDED => 'success',
            self::STATUS_ABORTED => 'failed',
            default => $status,
        };
    }

    private function toPublicApprovalStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'granted',
            'rejected' => 'rejected',
            'expired' => 'expired',
            default => 'pending',
        };
    }

    private function toApprovalStatus(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return match ($status) {
            'granted' => 'approved',
            default => $status,
        };
    }

    private function toWebhookStatus(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return $status;
    }

    private function toPublicWebhookStatus(string $status): string
    {
        return match ($status) {
            'delivering' => 'pending',
            default => $status,
        };
    }

    /**
     * @param  list<string>  $runIds
     * @return array<string, int>
     */
    private function stepCountsByRunId(array $runIds): array
    {
        if ($runIds === []) {
            return [];
        }

        $rows = DB::table('flow_steps')
            ->selectRaw('run_id, COUNT(*) as step_count')
            ->whereIn('run_id', $runIds)
            ->groupBy('run_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->run_id] = (int) $row->step_count;
        }

        return $counts;
    }

    private function runStepCount(string $runId): int
    {
        return (int) DB::table('flow_steps')
            ->where('run_id', $runId)
            ->count('id');
    }

    /**
     * @return array{avg:int, p95:int}
     */
    private function durationStatsForWindow(DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd): array
    {
        /** @var list<int> $durations */
        $durations = DB::table('flow_runs')
            ->select('duration_ms')
            ->where('started_at', '>=', $this->dbTimestamp($windowStart))
            ->where('started_at', '<', $this->dbTimestamp($windowEnd))
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms')
            ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->toArray();

        if ($durations === []) {
            return ['avg' => 0, 'p95' => 0];
        }

        $avg = (int) round(array_sum($durations) / count($durations));

        return [
            'avg' => $avg,
            'p95' => $this->percentile($durations, 95),
        ];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitFlowDefinition(string $definition): array
    {
        if (str_contains($definition, ':')) {
            $parts = explode(':', $definition, 2);
            $name = trim($parts[0]);
            $version = trim($parts[1]);
            if ($name !== '' && $version !== '') {
                return [$name, $version];
            }
        }

        if (str_contains($definition, '@')) {
            $parts = explode('@', $definition, 2);
            $name = trim($parts[0]);
            $version = trim($parts[1]);
            if ($name !== '' && $version !== '') {
                return [$name, $version];
            }
        }

        return [$definition, 'v1.0'];
    }

    private function immutableDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        return null;
    }

    private function actorFromJson(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->extractActor($decoded) ?? $value;
            }

            return $value;
        }

        if (! is_array($value)) {
            return '—';
        }

        return $this->extractActor($value) ?? '—';
    }

    /**
     * @param  array<mixed>  $value
     */
    private function extractActor(array $value): ?string
    {
        foreach ($value as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }

            if (is_numeric($candidate)) {
                return (string) $candidate;
            }

            if (is_array($candidate)) {
                $nested = $this->extractActor($candidate);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function safePayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    private function dbTimestamp(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    private function rowValue(array|object $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row) && array_key_exists($key, $row)) {
            return $row[$key];
        }

        if (is_object($row) && property_exists($row, $key)) {
            return $row->{$key};
        }

        return $default;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, int $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values, SORT_NUMERIC);
        $index = (int) ceil((count($values) - 1) * ($percentile / 100));

        return (int) $values[$index];
    }

    private function likePattern(string $value): string
    {
        $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);

        return "%{$escaped}%";
    }

    private function flowDefinitionLike(string $value): string
    {
        return $this->escapeLike($value) . '%';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
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
}
