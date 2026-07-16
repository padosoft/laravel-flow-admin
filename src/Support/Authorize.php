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
            'edit_definition', 'edit-definition' => $authorizer->canEditDefinition(
                (string) ($context['flowName'] ?? $context['flow_name'] ?? ''),
                $actor,
            ),
            default => throw new HttpException(400, "Unsupported action: {$action}"),
        };

        if (! $allowed) {
            Log::warning('flow-admin authorization denied', [
                'action' => $action,
                'actor' => self::safeActor($actor),
                'context' => self::safeContext($context),
            ]);

            throw new HttpException(403, 'Action not authorized');
        }

        Log::info('flow-admin authorization granted', [
            'action' => $action,
            'actor' => self::safeActor($actor),
            'context' => self::safeContext($context),
        ]);

        return $operation();
    }

    /**
     * @param  array<string, mixed>|null  $actor
     * @return array<string, mixed>
     */
    private static function safeActor(?array $actor): array
    {
        if ($actor === null) {
            return [];
        }

        $safe = [];
        foreach (['id', 'type', 'role'] as $key) {
            if (! array_key_exists($key, $actor)) {
                continue;
            }

            $value = $actor[$key];
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, scalar|null>
     */
    private static function safeContext(array $context): array
    {
        $safe = [];

        foreach (['runId', 'run_id', 'outboxId', 'outbox_id', 'flowName', 'flow_name'] as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        if (array_key_exists('tokenHash', $context) || array_key_exists('token_hash', $context)) {
            $tokenHash = (string) ($context['tokenHash'] ?? $context['token_hash'] ?? '');
            $safe['token_hash'] = self::obfuscate($tokenHash);
        }

        return $safe;
    }

    private static function obfuscate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . '...' . substr($value, -4);
    }
}
