<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Authorizers;

use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;

/**
 * Permissive opt-in `ActionAuthorizer`, mirroring the upstream
 * `padosoft/laravel-flow` `Dashboard\Authorization\AllowAllAuthorizer`.
 *
 * NOT the default — {@see DenyAllAuthorizer} is, so production deployments
 * cannot accidentally expose Studio editing or dashboard mutations. Bind
 * this explicitly only for local development or E2E fixtures (see
 * `testbench.yaml`'s `FLOW_ADMIN_AUTHORIZER` override), never in
 * production.
 */
final class AllowAllAuthorizer implements ActionAuthorizer
{
    public function canViewRuns(?array $actor): bool
    {
        return true;
    }

    public function canViewRunDetail(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canReplayRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canApproveByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canRejectByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canCancelRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canRetryWebhook(int $outboxId, ?array $actor): bool
    {
        return true;
    }

    public function canViewKpis(?array $actor): bool
    {
        return true;
    }

    public function canEditDefinition(string $flowName, ?array $actor): bool
    {
        return true;
    }
}
