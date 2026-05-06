<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\OutboxEntry;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;

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
}
