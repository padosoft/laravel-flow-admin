<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

use Illuminate\Support\Facades\Log;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralizes admin action authorization checks so every mutation path can be
 * wrapped consistently.
 *
 * `action()` executes the provided callback only if the current `ActionAuthorizer`
 * allows the operation. The helper logs both allow and deny outcomes.
 */
final class Authorize
{
    /**
     * @param  array<string, mixed>|null  $actor
     * @param  array<string, mixed>  $context
     */
    public static function action(
        string $action,
        callable $operation,
        ?array $actor = null,
        array $context = [],
    ): mixed {
        $authorizer = app(ActionAuthorizer::class);

        $allowed = match ($action) {
            'view_runs', 'view-runs' => $authorizer->canViewRuns($actor),
            'view_run_detail', 'view-run-detail' => $authorizer->canViewRunDetail(
                (string) ($context['runId'] ?? $context['run_id'] ?? ''),
                $actor,
            ),
            'replay_run', 'replay-run' => $authorizer->canReplayRun(
                (string) ($context['runId'] ?? $context['run_id'] ?? ''),
                $actor,
            ),
            'approve', 'approve_by_token', 'approve-by-token' => $authorizer->canApproveByToken(
                (string) ($context['tokenHash'] ?? $context['token_hash'] ?? ''),
                $actor,
            ),
            'reject', 'reject_by_token', 'reject-by-token' => $authorizer->canRejectByToken(
                (string) ($context['tokenHash'] ?? $context['token_hash'] ?? ''),
                $actor,
            ),
            'cancel_run', 'cancel-run' => $authorizer->canCancelRun(
                (string) ($context['runId'] ?? $context['run_id'] ?? ''),
                $actor,
            ),
            'retry_webhook', 'retry-webhook' => $authorizer->canRetryWebhook(
                (int) ($context['outboxId'] ?? $context['outbox_id'] ?? 0),
                $actor,
            ),
            'view_kpis', 'view-kpis' => $authorizer->canViewKpis($actor),
            default => throw new HttpException(400, "Unsupported action: {$action}"),
        };

        if (! $allowed) {
            Log::warning('flow-admin authorization denied', [
                'action' => $action,
                'actor' => $actor,
                'context' => $context,
            ]);

            throw new HttpException(403, 'Action not authorized');
        }

        Log::info('flow-admin authorization granted', [
            'action' => $action,
            'actor' => $actor,
            'context' => $context,
        ]);

        return $operation();
    }
}
