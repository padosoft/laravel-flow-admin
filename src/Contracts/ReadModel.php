<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\FlowDefinition;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\OutboxEntry;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ThroughputBucket;

/**
 * Public surface for data sources used by admin pages and services.
 *
 * Filters are intentionally minimal to keep the contract stable in v0.1.
 * `status`, `flow`, and `query` are optional and can be null.
 */
interface ReadModel
{
    /**
     * @param  ?string  $status  Filter by normalized status (`running|success|failed|paused|pending|compensated`).
     * @param  ?string  $flow  Filter by flow definition name (`flow.definition`).
     * @param  ?string  $query  Search term over run id, definition, or correlation.
     * @return PaginatedResult<RunSummary>
     */
    public function listRuns(?string $status = null, ?string $flow = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult;

    public function findRun(string $runId): ?RunDetail;

    /**
     * @param  ?string  $status  `pending|granted|rejected|expired`.
     * @param  ?string  $query  Search term over run id, step name, or token id.
     * @return PaginatedResult<ApprovalSummary>
     */
    public function listApprovals(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult;

    /**
     * @return list<ApprovalSummary>
     */
    public function pendingApprovals(int $limit = 25): array;

    /**
     * @param  ?string  $status  `pending|delivering|delivered|failed`.
     * @param  ?string  $query  Search term over run id or event.
     * @return PaginatedResult<OutboxEntry>
     */
    public function listWebhookOutbox(?string $status = null, ?string $query = null, int $page = 1, int $perPage = 25): PaginatedResult;

    /**
     * @return list<OutboxEntry>
     */
    public function pendingWebhookOutbox(): array;

    public function kpis(): KpiSummary;

    /**
     * @return list<ThroughputBucket>
     */
    public function throughputBuckets(): array;

    /**
     * @return list<FlowDefinition>
     */
    public function definitions(): array;

    /**
     * The latest PUBLISHED graph for a flow definition, ready for the
     * Studio canvas — the graph envelope plus a catalog subset covering
     * only the node types the graph actually uses (so the response stays
     * small regardless of how many node types the engine has registered).
     * `null` when the definition doesn't exist or has no published version.
     *
     * `graph` is core's `GraphSerializer::toArray()` envelope verbatim
     * (`{schema_version, kind, metadata, nodes: [{id, type, config,
     * position}], connections: [{sourceNodeId, sourcePortKey, targetNodeId,
     * targetPortKey}]}`) — deliberately left as `array<string, mixed>`
     * rather than a strict shape here: it is core's contract to validate,
     * not something this adapter re-verifies on every read. `catalog` maps
     * node type => `NodeDefinition::toArray()`'s rendering-relevant subset
     * (`{type, name, category, icon, description, inputs, outputs}`, each
     * port `{key, type, required, label, multiple}`).
     *
     * @return null|array{graph: array<string, mixed>, catalog: array<string, array<string, mixed>>}
     */
    public function graph(string $name): ?array;
}
