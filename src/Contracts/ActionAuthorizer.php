<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts;

/**
 * Action authorization contract for admin page routes and mutation endpoints.
 *
 * The concrete implementation is host-app specific in production. The
 * package ships a deny-by-default implementation for safe defaults.
 */
interface ActionAuthorizer
{
    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canViewRuns(?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canViewRunDetail(string $runId, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canReplayRun(string $runId, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canApproveByToken(string $tokenHash, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canRejectByToken(string $tokenHash, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canCancelRun(string $runId, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canRetryWebhook(int $outboxId, ?array $actor): bool;

    /**
     * @param  array<string, mixed>|null  $actor
     */
    public function canViewKpis(?array $actor): bool;
}
