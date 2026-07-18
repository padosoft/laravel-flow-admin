<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts;

/**
 * Action authorization contract for admin page routes and mutation endpoints.
 *
 * The concrete implementation is host-app specific in production. The
 * package ships a deny-by-default implementation for safe defaults.
 *
 * ENFORCEMENT POSTURE — read this before implementing:
 * The panel is "read-only by default": with the shipped `DenyAllAuthorizer`
 * you can BROWSE production data on day 1, and every MUTATION is denied. So the
 * package's controllers wire the authorizer on the mutation/authoring surfaces
 * ONLY — {@see self::canEditDefinition()} (edit-graph/draft/publish/dry-run/
 * ai-build/advisor-scan), {@see self::canCancelRun()}, {@see self::canReplayRun()},
 * {@see self::canApproveByToken()}, {@see self::canRejectByToken()},
 * {@see self::canRetryWebhook()}.
 * The VIEW methods below — {@see self::canViewRuns()}, {@see self::canViewRunDetail()},
 * {@see self::canViewKpis()} — are RESERVED forward-looking hooks: they are NOT
 * yet invoked by any controller, because enforcing them under the default
 * deny-all would contradict the day-1-browse promise (an unimplemented
 * authorizer would then hide everything). Implementing them today has no
 * effect until per-view enforcement is wired (tracked follow-up). Do not rely
 * on them for access control yet; put view/tenant scoping in your route
 * middleware for now.
 */
interface ActionAuthorizer
{
    /**
     * RESERVED — not yet enforced by any controller (see the interface-level
     * "enforcement posture" note). Reserved for opt-in per-actor run-list
     * visibility; today the runs list is open to anyone past the route-group
     * `auth` middleware.
     *
     * @param  array<string, mixed>|null  $actor
     */
    public function canViewRuns(?array $actor): bool;

    /**
     * RESERVED — not yet enforced by any controller (see the interface-level
     * "enforcement posture" note). Reserved for opt-in per-run view
     * authorization (e.g. multi-tenant isolation) on the run-detail page and
     * the live-monitor endpoints; today those are open to anyone past the
     * route-group `auth` middleware.
     *
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
     * RESERVED — not yet enforced by any controller (see the interface-level
     * "enforcement posture" note). Reserved for opt-in KPI-tile visibility;
     * today the overview KPIs are open to anyone past the route-group `auth`
     * middleware.
     *
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
