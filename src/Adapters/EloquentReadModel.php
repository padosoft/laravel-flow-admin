<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Adapters;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
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
use Padosoft\LaravelFlow\Graph\StoredDefinition;
use Padosoft\LaravelFlow\Node\NodeDefinition;
use Padosoft\LaravelFlow\Node\NodeRegistry;
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
use Throwable;

/**
 * Routes every read exclusively through core's `@api` `FlowDashboardReadModel`
 * (+ `Contracts\DefinitionRepository` for declared-step counts and published
 * graphs, `Node\NodeRegistry` for the Studio canvas's node catalog — the
 * named exceptions to "Dashboard\* only", see `AGENTS.md`) — never raw
 * query-builder calls against the `flow_*` tables.
 *
 * `FlowDashboardReadModel`'s filter DTOs match on EXACT equality only (no
 * free-text substring search, no OR-of-statuses, no distinct-name listing,
 * no flow-name PREFIX match). Plain listing and single-exact-status
 * filtering (`listRuns`/`listApprovals`/`listWebhookOutbox` with no free-text
 * `query` — and for `listRuns` specifically, no compound admin status like
 * `'failed'` and no flow-prefix filter) delegate straight to
 * `FlowDashboardReadModel`'s own server-side pagination — NOT bounded, full
 * history. Only a free-text search (or, for runs, a compound status /
 * flow-prefix filter) falls back to fetching a BOUNDED, most-recent batch
 * (`self::RECENT_BATCH_CAP` runs — the same ceiling `Dashboard\Pagination::MAX_PER_PAGE`
 * already imposes on core's own contract) and filtering/aggregating in PHP,
 * since core's filter DTOs cannot express those queries server-side. This is
 * a real, deliberate scope boundary, not silently pretended away — see
 * `laravel-flow-ai`'s `FlowAdvisor::candidateDefinitionNames()` for the
 * identical tradeoff in a sibling package. KPIs and throughput buckets do
 * NOT share this bound at all — {@see self::runsInWindow()} pages through
 * every run in the requested window (up to `WINDOW_PAGE_SAFETY_CAP`, logged
 * if ever hit) instead of stopping at `RECENT_BATCH_CAP`.
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
     * bound), KPI aggregates must reflect the full window population — this
     * only guards against an unbounded loop on a pathologically busy
     * window. 50 * RECENT_BATCH_CAP = 10,000 runs per window — well beyond
     * any real 24h/48h rolling window this program's installs run at
     * today. If it's ever actually hit, `runsInWindow()` logs a warning
     * (not silent) and degrades to the partial set rather than looping
     * forever or throwing.
     */
    private const WINDOW_PAGE_SAFETY_CAP = 50;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $clock  Injectable "now" for
     *                                                      the KPI time windows — defaults to the system UTC clock. A test can pass
     *                                                      a fixed instant so a run seeded exactly at a window boundary is
     *                                                      classified deterministically (the window edges are derived from the SAME
     *                                                      instant the query uses), removing the microsecond race between the two.
     */
    public function __construct(
        private FlowDashboardReadModel $reader,
        private DefinitionRepository $definitions,
        private NodeRegistry $nodeRegistry,
        private readonly ?Closure $clock = null,
    ) {}

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null
            ? ($this->clock)()
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function listRuns(?string $status = null, ?string $flow = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $engineStatuses = $this->toEngineStatuses($status);
        $flowFilter = trim((string) $flow);
        $search = trim((string) $query);

        // A single exact engine status (or none) can be pushed straight
        // into RunFilter for TRUE server-side pagination — no RECENT_BATCH_CAP
        // involved, so this covers the common "browse everything" /
        // "filter by one status" cases at full scale. Only a compound
        // status (admin 'failed' maps to two engine statuses — no OR
        // support in RunFilter), a flow prefix filter, or free-text search
        // fall back to the bounded batch-and-filter-in-PHP path, since none
        // of those can be expressed as a single RunFilter.
        if (! is_array($engineStatuses) && $flowFilter === '' && $search === '') {
            $paged = $this->reader->listRuns(new RunFilter(status: $engineStatuses), new Pagination($page, $perPage));

            $stepCounts = $this->reader->stepCounts(array_map(static fn (DashboardRunSummary $run): string => $run->id, $paged->items));

            return new PaginatedResult(
                items: array_map(fn (DashboardRunSummary $run): RunSummary => $this->mapRunSummary($run, $stepCounts[$run->id] ?? 0), $paged->items),
                total: $paged->total,
                page: $paged->page,
                perPage: $paged->perPage,
            );
        }

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
        $pageItems = array_slice($filtered, $this->offset($page, $perPage), $perPage);

        // Batch step counts for the whole page in ONE query instead of one
        // findRun() (5 queries + full run-detail hydration) per row.
        $stepCounts = $this->reader->stepCounts(array_map(static fn (DashboardRunSummary $run): string => $run->id, $pageItems));

        $mapped = array_map(
            fn (DashboardRunSummary $run): RunSummary => $this->mapRunSummary($run, $stepCounts[$run->id] ?? 0),
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

        // ApprovalFilter's status is always a single exact match (no
        // OR-of-statuses like listRuns()'s admin 'failed'), so absent a
        // free-text search this can ALWAYS be true server-side pagination
        // — no RECENT_BATCH_CAP involved.
        if ($search === '') {
            $paged = $this->reader->listApprovals($filter, new Pagination($page, $perPage));

            return new PaginatedResult(
                items: array_map($this->mapApproval(...), $paged->items),
                total: $paged->total,
                page: $paged->page,
                perPage: $paged->perPage,
            );
        }

        $candidates = $this->reader->listApprovals($filter, new Pagination(1, self::RECENT_BATCH_CAP))->items;
        $filtered = array_values(array_filter($candidates, fn (DashboardApprovalSummary $a): bool => $this->approvalMatchesSearch($a, $search)));

        $total = count($filtered);
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

        // WebhookOutboxFilter's status is always a single exact match, so
        // absent a free-text search this can ALWAYS be true server-side
        // pagination — no RECENT_BATCH_CAP involved.
        if ($search === '') {
            $paged = $this->reader->listWebhookOutbox($filter, new Pagination($page, $perPage));

            return new PaginatedResult(
                items: array_map($this->mapOutbox(...), $paged->items),
                total: $paged->total,
                page: $paged->page,
                perPage: $paged->perPage,
            );
        }

        // `FlowDashboardReadModel::listWebhookOutbox()` already orders by
        // `orderByDesc('id')` (newest first) — no re-reversal needed here.
        $candidates = $this->reader->listWebhookOutbox($filter, new Pagination(1, self::RECENT_BATCH_CAP))->items;
        $filtered = array_values(array_filter($candidates, fn (DashboardWebhookOutboxSummary $o): bool => $this->outboxMatchesSearch($o, $search)));

        $total = count($filtered);
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
        $windowNow = $this->now();
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
        $now = $this->now();
        $since = $now->sub(new DateInterval('PT' . self::THROUGHPUT_WINDOW_HOURS . 'H'));
        $runs = $this->runsInWindow($since, $now);

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

    public function graph(string $name): ?array
    {
        try {
            $stored = $this->definitions->latest($name, StoredDefinition::STATUS_PUBLISHED);
        } catch (Throwable $e) {
            // Fail closed (the controller reports a routine 404, same as
            // "not published") — but unlike declaredStepCount()'s "degrade
            // a metric to 0" case, a failure here can mean the stored
            // definition's signature verification failed (tampering, or a
            // broken signing-secret rotation), which an operator needs to
            // be able to notice. Log the message only — never the
            // exception's full context, which could carry the stored
            // graph's contents.
            Log::warning('laravel-flow-admin: failed to resolve the published graph for a flow definition', [
                'name' => $name,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($stored === null) {
            return null;
        }

        return [
            'graph' => GraphRedactor::stripNodeConfig($stored->graph),
            'catalog' => $this->catalogForUsedNodeTypes($stored->graph),
        ];
    }

    public function editableGraph(string $name): ?array
    {
        try {
            // No status filter (unlike graph()): the editor resumes
            // whatever the latest version is, draft or published, so a
            // save-as-draft continues from where the flow's author left
            // off rather than always re-branching from the published one.
            $stored = $this->definitions->latest($name);
        } catch (Throwable $e) {
            Log::warning('laravel-flow-admin: failed to resolve the latest graph for a flow definition', [
                'name' => $name,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($stored === null) {
            return null;
        }

        return [
            'graph' => $stored->graph,
            'catalog' => $this->catalogForUsedNodeTypes($stored->graph),
            'version' => $stored->version,
            'status' => $stored->status,
        ];
    }

    public function catalog(): array
    {
        $catalog = [];

        foreach ($this->nodeRegistry->all() as $type => $definition) {
            $catalog[$type] = $this->nodeDefinitionToArray($definition);
        }

        return $catalog;
    }

    /**
     * @param  array<string, mixed>  $graph  a `GraphSerializer::toArray()` envelope
     * @return array<string, array<string, mixed>>
     */
    private function catalogForUsedNodeTypes(array $graph): array
    {
        $nodes = $graph['nodes'] ?? null;
        $usedTypes = is_array($nodes)
            ? array_unique(array_values(array_filter(array_map(
                static fn (mixed $node): ?string => is_array($node) && is_string($node['type'] ?? null) ? $node['type'] : null,
                $nodes,
            ))))
            : [];

        // Deliberately outside any DefinitionRepository try/catch: NodeRegistry::has()/
        // get() are in-memory array lookups over types registered at boot
        // time, not I/O — they don't throw, so there's nothing here for a
        // fail-closed-to-404 handler to catch.
        $catalog = [];
        foreach ($usedTypes as $type) {
            if (! $this->nodeRegistry->has($type)) {
                continue;
            }

            $catalog[$type] = $this->nodeDefinitionToArray($this->nodeRegistry->get($type));
        }

        return $catalog;
    }

    /**
     * @return array{type: string, name: string, category: string, icon: ?string, description: ?string, inputs: list<array{key: string, type: string, required: bool, label: string, multiple: bool}>, outputs: list<array{key: string, type: string, required: bool, label: string, multiple: bool}>}
     */
    private function nodeDefinitionToArray(NodeDefinition $definition): array
    {
        // NodeDefinition::toArray() is a superset (also carries retry/cacheable/cost,
        // server-side execution metadata the canvas doesn't need) — keep the
        // Studio catalog response to exactly the rendering-relevant subset.
        $full = $definition->toArray();

        return [
            'type' => $full['type'],
            'name' => $full['name'],
            'category' => $full['category'],
            'icon' => $full['icon'],
            'description' => $full['description'],
            'inputs' => $full['inputs'],
            'outputs' => $full['outputs'],
        ];
    }

    /**
     * The flow's DECLARED node count (its latest stored graph), not a
     * count of step EXECUTIONS across run history — the old raw-SQL query
     * actually computed the latter despite `FlowDefinition::$stepCount`'s
     * own docblock promising the former, a bug this rewrite does not
     * reproduce. Reads `$stored->graph['nodes']` directly rather than
     * fully deserializing into a `GraphDefinition` — this method only
     * needs a count, not an executable graph.
     *
     * `DefinitionRepository::latest()` can throw (signature verification
     * failure, connection issues) for a single misconfigured/corrupted
     * definition row; one bad row must not 500 the whole definitions list,
     * so this degrades to 0 on failure — same defensive pattern as
     * `OverviewController::safe()`.
     */
    private function declaredStepCount(string $name): int
    {
        try {
            $stored = $this->definitions->latest($name);
        } catch (Throwable) {
            return 0;
        }

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

        if (count($runs) < $total) {
            // Not silently pretended away: a window this busy (more than
            // WINDOW_PAGE_SAFETY_CAP * RECENT_BATCH_CAP runs) is beyond
            // what this in-PHP aggregation can page through without an
            // unbounded loop — log so it's observable, then degrade to
            // the partial set rather than hang or throw.
            Log::warning('laravel-flow-admin: KPI/throughput window truncated', [
                'window_start' => $start->format(DateTimeInterface::ATOM),
                'window_end' => $end->format(DateTimeInterface::ATOM),
                'runs_fetched' => count($runs),
                'runs_total' => $total,
            ]);
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
            // The token HASH (not the plaintext token) — the key the dashboard
            // authorizes on and passes to Flow::resumeByHash()/rejectByHash().
            tokenHash: $approval->tokenHash,
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
            cacheHit: $step->cacheHit,
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

    private function offset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }
}
