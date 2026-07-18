<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Authorizers;

use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;

/**
 * Default deny-by-default action authorizer for dashboard endpoints.
 *
 * This keeps the dashboard mutation surface closed unless the host app
 * explicitly overrides the binding (for example in a service provider).
 */
final class DenyAllAuthorizer implements ActionAuthorizer
{
    public function canViewRuns(?array $actor): bool
    {
        return false;
    }

    public function canViewRunDetail(string $runId, ?array $actor): bool
    {
        return false;
    }

    public function canReplayRun(string $runId, ?array $actor): bool
    {
        return false;
    }

    public function canApproveByToken(string $tokenHash, ?array $actor): bool
    {
        return false;
    }

    public function canRejectByToken(string $tokenHash, ?array $actor): bool
    {
        return false;
    }

    public function canCancelRun(string $runId, ?array $actor): bool
    {
        return false;
    }

    public function canRetryWebhook(int $outboxId, ?array $actor): bool
    {
        return false;
    }

    public function canViewKpis(?array $actor): bool
    {
        return false;
    }

    public function canEditDefinition(string $flowName, ?array $actor): bool
    {
        return false;
    }
}
