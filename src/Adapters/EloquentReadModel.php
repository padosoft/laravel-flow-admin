<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Adapters;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Dashboard\ApprovalFilter;
use Padosoft\LaravelFlow\Dashboard\ApprovalSummary as DashboardApprovalSummary;
use Padosoft\LaravelFlow\Dashboard\AuditEntry;
use Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel;
use Padosoft\LaravelFlow\Dashboard\Pagination;
use Padosoft\LaravelFlow\Dashboard\RunDetail as DashboardRunDetail;
use Padosoft\LaravelFlow\Dashboard\RunFilter;
use Padosoft\LaravelFlow\Dashboard\RunSummary as DashboardRunSummary;
use Padosoft\LaravelFlow\Dashboard\StepSummary;
use Padosoft\LaravelFlow\Dashboard\WebhookOutboxFilter;
use Padosoft\LaravelFlow\Dashboard\WebhookOutboxSummary as DashboardWebhookOutboxSummary;
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

/**
 * Routes every read exclusively through core's `@api` `FlowDashboardReadModel`
 * (+ `DefinitionRepository` for declared-step counts) — never raw query-builder
 * calls against the `flow_*` tables. `FlowDashboardReadModel`'s filter DTOs match
 * on EXACT equality only (no free-text substring search, no OR-of-statuses,
 * no distinct-name listing), so several admin features that the old
 * raw-SQL adapter implemented at the database level are now implemented by
 * fetching a BOUNDED, most-recent batch (`self::RECENT_BATCH_CAP` runs —
 * the same ceiling `Dashboard\Pagination::MAX_PER_PAGE` already imposes on
 * core's own contract) and filtering/aggregating in PHP. This is a real,
 * deliberate scope boundary, not silently pretended away: search, flow
 * filtering, and the definitions list only see the most recent
 * `RECENT_BATCH_CAP` runs, not full history. Every other satellite package
 * in this program accepts the identical bound for identical reasons (see
 * `laravel-flow-ai`'s `FlowAdvisor::candidateDefinitionNames()`). KPIs and
 * throughput buckets do NOT share this bound — {@see self::runsInWindow()}
 * pages through every run in the requested window instead of stopping at
 * `RECENT_BATCH_CAP`, since those are numeric aggregates rather than a
 * "recent list" UX affordance.
 */
final readonly class EloquentReadModel implements ReadModel
{
    private const STATUS_SUCCEEDED = 'succeeded';

    private const STATUS_FAILED = 'failed';

    private const STATUS_ABORTED = 'aborted';

    /**
     * Bound on how far back "recent" reads (search, flow filtering, the
     * definitions list) look — matches `Dashboard\Pagination::MAX_PER_PAGE`,
     * the ceiling a single `FlowDashboardReadModel::listRuns()` call can
     * ever return in one page.
     */
    private const RECENT_BATCH_CAP = 200;

    private const THROUGHPUT_WINDOW_HOURS = 48;

    /**
     * Defensive ceiling on how many `RECENT_BATCH_CAP`-sized pages
     * `runsInWindow()` will fetch while paging through a KPI/throughput
     * window. Unlike `RECENT_BATCH_CAP` (an intentional "recent list" UX
     * bound), KPI aggregates must reflect the full window population —
     * this only guards against a pathologically busy window running away.
     * 20 * RECENT_BATCH_CAP = 4,000 runs per window.
     */
    private const WINDOW_PAGE_SAFETY_CAP = 20;

    public function __construct(
        private FlowDashboardReadModel $reader,
        private DefinitionRepository $definitions,
    ) {}

    public function listRuns(?string $status = null, ?string $flow = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $engineStatuses = $this->toEngineStatuses($status);
        $flowFilter = trim((string) $flow);
        $search = trim((string) $query);

        $filtered = array_values(array_filter(
            $this->recentRuns(),
            function (DashboardRunSummary $run) use ($engineStatuses, $flowFilter, $search): bool {
                if ($engineStatuses !== null) {
                    $matches = is_array($engineStatuses)
                        ? in_array($run->status, $engineStatuses, true)
                        : $run->status === $engineStatuses;

                    if (! $matches) {
                        return false;
                    }
                }

                if ($flowFilter !== '' && ! $this->definitionNameMatchesFlow($run->definitionName, $flowFilter)) {
                    return false;
                }

                if ($search !== '' && ! $this->runMatchesSearch($run, $search)) {
                    return false;
                }

                return true;
            },
        ));

        $total = count($filtered);
        $page = $this->pageInBounds($page, $perPage, $total);
        $pageItems = array_slice($filtered, $this->offset($page, $perPage), $perPage);

        $mapped = array_map(
            fn (DashboardRunSummary $run): RunSummary => $this->mapRunSummary($run, $this->stepCount($run->id)),
            $pageItems,
        );

        return new PaginatedResult($mapped, $total, $page, $perPage);
    }

    public function findRun(string $runId): ?RunDetail
    {
        $detail = $this->reader->findRun($runId);

        if (! ($detail instanceof DashboardRunDetail)) {
            return null;
        }

        $steps = array_map($this->mapStep(...), $detail->steps);
        $audit = array_map($this->mapAuditEvent(...), $detail->audit);

        return new RunDetail(
            summary: $this->mapRunSummary($detail->run, count($detail->steps), $this->sumAttempts($detail->steps)),
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
        $engineStatus = $this->toApprovalStatus($status);
        $search = trim((string) $query);

        $filter = new ApprovalFilter(status: $engineStatus);
        $candidates = $this->reader->listApprovals($filter, new Pagination(1, self::RECENT_BATCH_CAP))->items;

        $filtered = $search === ''
            ? $candidates
            : array_values(array_filter($candidates, fn (DashboardApprovalSummary $a): bool => $this->approvalMatchesSearch($a, $search)));

        $total = count($filtered);
        $page = $this->pageInBounds($page, $perPage, $total);
        $pageItems = array_slice($filtered, $this->offset($page, $perPage), $perPage);

        return new PaginatedResult(
            items: array_map($this->mapApproval(...), $pageItems),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function pendingApprovals(int $limit = 25): array
    {
        $limit = $this->normalizeLimit($limit);

        return array_map($this->mapApproval(...), $this->reader->pendingApprovals($limit));
    }

    public function listWebhookOutbox(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $engineStatus = $this->toWebhookStatus($status);
        $search = trim((string) $query);

        $filter = new WebhookOutboxFilter(status: $engineStatus);
        $candidates = $this->reader->listWebhookOutbox($filter, new Pagination(1, self::RECENT_BATCH_CAP))->items;

        $filtered = $search === ''
            ? $candidates
            : array_values(array_filter($candidates, fn (DashboardWebhookOutboxSummary $o): bool => $this->outboxMatchesSearch($o, $search)));

        // `FlowDashboardReadModel::listWebhookOutbox()` already orders by
        // `orderByDesc('id')` (newest first) — no re-reversal needed here.
        $total = count($filtered);
        $page = $this->pageInBounds($page, $perPage, $total);
        $pageItems = array_slice($filtered, $this->offset($page, $perPage), $perPage);

        return new PaginatedResult(
            items: array_map($this->mapOutbox(...), $pageItems),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function pendingWebhookOutbox(): array
    {
        $pending = $this->reader->pendingWebhookOutbox();
        $delivering = $this->reader->listWebhookOutbox(new WebhookOutboxFilter(status: 'delivering'), new Pagination(1, self::RECENT_BATCH_CAP))->items;

        // Both `$pending` and `$delivering` already come back newest-first
        // (`orderByDesc('id')` in core); concatenating keeps pending items
        // ahead of delivering items, which is the intended review priority.
        return array_map($this->mapOutbox(...), [...$pending, ...$delivering]);
    }

    public function kpis(): KpiSummary
    {
        $windowNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $windowStart = $windowNow->sub(new DateInterval('P1D'));
        $prevWindowStart = $windowStart->sub(new DateInterval('P1D'));

        // The previous window's end is exclusive (one second short of
        // $windowStart): both RunFilter::$startedSince and $startedUntil
        // are inclusive in core, so without this a run started exactly at
        // $windowStart would be counted in BOTH windows.
        $window = $this->runsInWindow($windowStart, $windowNow);
        $previous = $this->runsInWindow($prevWindowStart, $windowStart->sub(new DateInterval('PT1S')));

        $windowRates = $this->windowRates($window);
        $previousRates = $this->windowRates($previous);
        $duration = $this->durationStats($window);
        $previousDuration = $this->durationStats($previous);

        $windowRate = $this->ratio($windowRates['success'], $windowRates['total']);
        $previousRate = $this->ratio($previousRates['success'], $previousRates['total']);

        return new KpiSummary(
            totalRuns: $windowRates['total'],
            deltaTotalRuns: $windowRates['total'] - $previousRates['total'],
            successRate: $windowRate,
            deltaSuccessRate: $windowRate - $previousRate,
            failedRuns: $windowRates['failed'],
            deltaFailedRuns: $windowRates['failed'] - $previousRates['failed'],
            avgDurationMs: $duration['avg'],
            deltaAvgDurationMs: $duration['avg'] - $previousDuration['avg'],
            p95DurationMs: $duration['p95'],
        );
    }

    public function throughputBuckets(): array
    {
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->sub(new DateInterval('PT' . self::THROUGHPUT_WINDOW_HOURS . 'H'));
        $runs = $this->runsInWindow($since, new DateTimeImmutable('now', new DateTimeZone('UTC')));

        /** @var array<string, array{success: int, failed: int}> $byHour */
        $byHour = [];

        foreach ($runs as $run) {
            $bucketKey = $run->startedAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:00:00');

            if ($bucketKey === null) {
                continue;
            }

            $byHour[$bucketKey] ??= ['success' => 0, 'failed' => 0];

            if ($run->status === self::STATUS_SUCCEEDED) {
                $byHour[$bucketKey]['success']++;
            } elseif (in_array($run->status, [self::STATUS_FAILED, self::STATUS_ABORTED], true)) {
                $byHour[$bucketKey]['failed']++;
            }
        }

        ksort($byHour);

        $result = [];
        foreach ($byHour as $bucketKey => $counts) {
            $at = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $bucketKey, new DateTimeZone('UTC'));

            if ($at === false) {
                continue;
            }

            $result[] = new ThroughputBucket(at: $at, successCount: $counts['success'], failedCount: $counts['failed']);
        }

        return $result;
    }

    public function definitions(): array
    {
        $runs = $this->recentRuns();

        /** @var array<string, list<DashboardRunSummary>> $byName */
        $byName = [];
        foreach ($runs as $run) {
            [$name] = $this->splitFlowDefinition($run->definitionName);
            $byName[$name][] = $run;
        }

        ksort($byName);

        $definitions = [];
        foreach ($byName as $name => $runsForName) {
            [, $version] = $this->splitFlowDefinition($runsForName[0]->definitionName);
            $totalRuns = count($runsForName);
            $successRuns = count(array_filter($runsForName, fn (DashboardRunSummary $r): bool => $r->status === self::STATUS_SUCCEEDED));

            $definitions[] = new FlowDefinition(
                name: $name,
                version: $version,
                stepCount: $this->declaredStepCount($name),
                totalRuns: $totalRuns,
                successRate: $totalRuns > 0 ? $successRuns / $totalRuns : 0.0,
            );
        }

        return $definitions;
    }

    /**
     * The flow's DECLARED node count (its latest stored graph), not a
     * count of step EXECUTIONS across run history — the old raw-SQL query
     * actually computed the latter despite `FlowDefinition::$stepCount`'s
     * own docblock promising the former, a bug this rewrite does not
     * reproduce. Reads `$stored->graph['nodes']` directly rather than
     * fully deserializing into a `GraphDefinition` — this method only
     * needs a count, not an executable graph.
     */
    private function declaredStepCount(string $name): int
    {
        $stored = $this->definitions->latest($name);

        if ($stored === null) {
            return 0;
        }

        $nodes = $stored->graph['nodes'] ?? null;

        return is_array($nodes) ? count($nodes) : 0;
    }

    /**
     * @return list<DashboardRunSummary>
     */
    private function recentRuns(): array
    {
        return $this->reader->listRuns(new RunFilter, new Pagination(1, self::RECENT_BATCH_CAP))->items;
    }

    /**
     * Unlike `recentRuns()` (an intentionally bounded "recent list" UX
     * scope), KPI and throughput aggregates must reflect the full window
     * population — this pages through every match up to
     * `WINDOW_PAGE_SAFETY_CAP` pages.
     *
     * @return list<DashboardRunSummary>
     */
    private function runsInWindow(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $filter = new RunFilter(startedSince: $start, startedUntil: $end);
        $runs = [];
        $page = 1;
        $total = null;

        while ($total === null || (count($runs) < $total && $page <= self::WINDOW_PAGE_SAFETY_CAP)) {
            $result = $this->reader->listRuns($filter, new Pagination($page, self::RECENT_BATCH_CAP));
            $runs = [...$runs, ...$result->items];
            $total = $result->total;
            $page++;

            if ($result->items === []) {
                break;
            }
        }

        return $runs;
    }

    /**
     * @param  list<DashboardRunSummary>  $runs
     * @return array{total:int, failed:int, success:int}
     */
    private function windowRates(array $runs): array
    {
        $failed = 0;
        $success = 0;

        foreach ($runs as $run) {
            if (in_array($run->status, [self::STATUS_FAILED, self::STATUS_ABORTED], true)) {
                $failed++;
            } elseif ($run->status === self::STATUS_SUCCEEDED) {
                $success++;
            }
        }

        return ['total' => count($runs), 'failed' => $failed, 'success' => $success];
    }

    /**
     * @param  list<DashboardRunSummary>  $runs
     * @return array{avg:int, p95:int}
     */
    private function durationStats(array $runs): array
    {
        $durations = array_values(array_filter(
            array_map(static fn (DashboardRunSummary $r): ?int => $r->durationMs, $runs),
            static fn (?int $d): bool => $d !== null && $d > 0,
        ));

        if ($durations === []) {
            return ['avg' => 0, 'p95' => 0];
        }

        return [
            'avg' => (int) round(array_sum($durations) / count($durations)),
            'p95' => $this->percentile($durations, 95),
        ];
    }

    private function stepCount(string $runId): int
    {
        $detail = $this->reader->findRun($runId);

        return $detail instanceof DashboardRunDetail ? count($detail->steps) : 0;
    }

    /**
     * @param  list<StepSummary>  $steps
     */
    private function sumAttempts(array $steps): int
    {
        // Dashboard's StepSummary carries no per-step attempt count (core's
        // read model does not expose retry-attempt tallies) — the old
        // raw-SQL adapter's own $attempts argument to mapRunSummary() also
        // just defaulted to the step count when not separately computed
        // (see its `pendingApprovals`/list-view call sites), so this
        // preserves that same fallback rather than inventing new precision
        // the underlying data does not have.
        return count($steps);
    }

    private function definitionNameMatchesFlow(string $definitionName, string $flowFilter): bool
    {
        return str_starts_with($definitionName, $flowFilter . ':') || str_starts_with($definitionName, $flowFilter . '@');
    }

    private function runMatchesSearch(DashboardRunSummary $run, string $search): bool
    {
        $needle = mb_strtolower($search);

        return str_contains(mb_strtolower($run->id), $needle)
            || str_contains(mb_strtolower($run->definitionName), $needle)
            || str_contains(mb_strtolower((string) $run->correlationId), $needle);
    }

    private function approvalMatchesSearch(DashboardApprovalSummary $approval, string $search): bool
    {
        $needle = mb_strtolower($search);

        return str_contains(mb_strtolower($approval->id), $needle)
            || str_contains(mb_strtolower($approval->runId), $needle)
            || str_contains(mb_strtolower($approval->stepName), $needle);
    }

    private function outboxMatchesSearch(DashboardWebhookOutboxSummary $outbox, string $search): bool
    {
        $needle = mb_strtolower($search);

        return str_contains(mb_strtolower((string) $outbox->runId), $needle)
            || str_contains(mb_strtolower($outbox->event), $needle);
    }

    private function mapRunSummary(DashboardRunSummary $run, int $stepCount, ?int $attempts = null): RunSummary
    {
        [$name, $version] = $this->splitFlowDefinition($run->definitionName);
        $correlationId = (string) $run->correlationId;

        return new RunSummary(
            id: $run->id,
            flowName: $name,
            flowVersion: $version,
            status: $this->toPublicStatus($run->status),
            actor: $correlationId !== '' ? $correlationId : 'system',
            correlationId: $correlationId,
            startedAt: $run->startedAt ?? new DateTimeImmutable,
            finishedAt: $run->finishedAt,
            durationMs: $run->durationMs,
            stepCount: $stepCount,
            attemptsTotal: $attempts ?? $stepCount,
        );
    }

    private function mapApproval(DashboardApprovalSummary $approval): ApprovalSummary
    {
        return new ApprovalSummary(
            tokenId: $approval->id,
            runId: $approval->runId,
            stepName: $approval->stepName,
            description: sprintf('Approval requested for %s', $approval->stepName !== '' ? $approval->stepName : 'run'),
            status: $this->toPublicApprovalStatus($approval->status),
            requestedAt: $approval->issuedAt ?? new DateTimeImmutable,
            approver: $this->extractActor($approval->actor ?? []),
            decidedAt: $approval->decidedAt,
        );
    }

    private function mapOutbox(DashboardWebhookOutboxSummary $outbox): OutboxEntry
    {
        $runId = $outbox->runId;
        $destination = $runId === null || $runId === '' ? $outbox->event : 'run:' . $runId;

        return new OutboxEntry(
            id: (string) $outbox->id,
            eventType: $outbox->event,
            destination: $destination,
            status: $this->toPublicWebhookStatus($outbox->status),
            attempts: $outbox->attempts,
            nextAttemptAt: $outbox->availableAt,
            lastError: $outbox->lastError,
        );
    }

    private function mapStep(StepSummary $step): Step
    {
        return new Step(
            name: $step->name,
            status: $step->status,
            startedAt: $step->startedAt,
            finishedAt: $step->finishedAt,
            durationMs: $step->durationMs,
            attempts: 1,
            dependsOn: [],
            errorMessage: $step->errorMessage,
        );
    }

    private function mapAuditEvent(AuditEntry $event): AuditEvent
    {
        return new AuditEvent(
            at: $event->occurredAt,
            type: $event->event,
            actor: 'system',
            payload: $event->payload ?? [],
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
        return $status === null || $status === '' ? null : $status;
    }

    private function toPublicWebhookStatus(string $status): string
    {
        return match ($status) {
            'delivering' => 'pending',
            default => $status,
        };
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

    private function extractActor(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

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

    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? $numerator / $denominator : 0.0;
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

    private function pageInBounds(int $page, int $perPage, int $total): int
    {
        if ($total === 0) {
            return 1;
        }

        $maxPage = (int) ceil($total / $perPage);

        return min($page, max(1, $maxPage));
    }

    private function offset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }
}
