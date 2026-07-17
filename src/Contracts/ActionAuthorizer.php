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

    /**
     * Gates both loading a flow definition's UNREDACTED graph for editing
     * (node `config` included — unlike the read-only Studio canvas's graph
     * endpoint, which redacts it) and saving an edited graph as a new draft
     * version. There is no dedicated upstream (`padosoft/laravel-flow`
     * `Dashboard\Authorization\DashboardActionAuthorizer`) equivalent —
     * that contract has no definition-editing concept — so this is
     * admin-package-local, same as {@see self::canCancelRun()} and
     * {@see self::canRetryWebhook()}.
     *
     * IMPORTANT — empty `$flowName`: the Flow Advisor scan
     * (`POST /advisor/scan`) authorizes an ALL-FLOWS action, so it calls this
     * with `$flowName === ''` (there is no single flow to name). A host
     * authorizer that scopes editing per-flow/per-tenant MUST special-case the
     * empty string deliberately — do NOT treat `''` as an unscoped allow.
     * Return `true` only if the actor may edit definitions across every flow
     * the scan could touch; return `false` to deny the scan entirely.
     *
     * @param  array<string, mixed>|null  $actor
     */
    public function canEditDefinition(string $flowName, ?array $actor): bool;
}
