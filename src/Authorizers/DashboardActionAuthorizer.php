<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Authorizers;

use Padosoft\LaravelFlow\Dashboard\Authorization\DashboardActionAuthorizer as UpstreamAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;

/**
 * Bridges the `padosoft/laravel-flow` public authorization contract into the
 * package-local action contract. This adapter keeps the package implementation
 * decoupled from the upstream class namespace while preserving existing
 * behavior defaults (`DenyAll` from upstream in non-test environments).
 */
final class DashboardActionAuthorizer implements ActionAuthorizer
{
    public function __construct(private UpstreamAuthorizer $upstream) {}

    public function canViewRuns(?array $actor): bool
    {
        return $this->upstream->canViewRuns($actor);
    }

    public function canViewRunDetail(string $runId, ?array $actor): bool
    {
        return $this->upstream->canViewRunDetail($runId, $actor);
    }

    public function canReplayRun(string $runId, ?array $actor): bool
    {
        return $this->upstream->canReplayRun($runId, $actor);
    }

    public function canApproveByToken(string $tokenHash, ?array $actor): bool
    {
        return $this->upstream->canApproveByToken($tokenHash, $actor);
    }

    public function canRejectByToken(string $tokenHash, ?array $actor): bool
    {
        return $this->upstream->canRejectByToken($tokenHash, $actor);
    }

    public function canCancelRun(string $runId, ?array $actor): bool
    {
        return false;
    }

    public function canRetryWebhook(int $outboxId, ?array $actor): bool
    {
        // No dedicated method exists on the upstream contract.
        return false;
    }

    public function canViewKpis(?array $actor): bool
    {
        return $this->upstream->canViewKpis($actor);
    }

    public function canEditDefinition(string $flowName, ?array $actor): bool
    {
        // No dedicated method exists on the upstream contract (it has no
        // definition-editing concept at all). A host app that wants Studio
        // editing enabled binds its own ActionAuthorizer implementation.
        return false;
    }
}
