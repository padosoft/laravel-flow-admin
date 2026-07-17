<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Padosoft\LaravelFlow\Exceptions\ApprovalPersistenceException;
use Padosoft\LaravelFlow\Exceptions\FlowExecutionException;
use Padosoft\LaravelFlow\Exceptions\FlowInputException;
use Throwable;

/**
 * Runs a core FlowEngine mutation and shapes its outcome into the admin's
 * uniform `{success, message, data}` JSON contract, mapping the engine's
 * typed exceptions to HTTP status codes so every mutation controller
 * (approve/reject, cancel, replay, redeliver) stays thin and consistent.
 *
 * Exception → status:
 *   - {@see FlowInputException}         → 422 (bad input, e.g. a blank token hash)
 *   - {@see ApprovalPersistenceException} → 503 (migrations missing / DB unreachable)
 *   - {@see FlowExecutionException}     → 409 (the action conflicts with the
 *                                          resource's current state: not found,
 *                                          already-decided, non-terminal, unpinned…)
 *   - any other {@see Throwable}        → 500 (sanitized; class-only log)
 *
 * The `FlowInputException`/`FlowExecutionException` messages are curated,
 * operator-facing `@api` strings that interpolate only ids already present in
 * the request URL (run id, flow name) — never secrets, and never a wrapped
 * cause (the engine passes causes via `previous:`, not into the message) — so
 * they are surfaced verbatim. The 503 and 500 paths use generic messages and
 * log the exception class only, never its message (which could carry DB/driver
 * internals).
 *
 * KNOWN LIMITATION (approvals-only 503): only the approval seams have a
 * dedicated `ApprovalPersistenceException` for "migrations missing / DB
 * unreachable", so only they map that infra failure to 503. `cancel()`,
 * `replay()` and `redeliverWebhook()` raise a plain `FlowExecutionException`
 * for BOTH ordinary state conflicts (not-found, non-terminal, unpinned) AND
 * genuine persistence-unavailability (via core's
 * `flowPersistenceUnavailableException()`), so both collapse to 409 here. The
 * operator still sees the real reason (the curated message is surfaced), but a
 * 409 reads as "not retryable" to automation. Splitting them requires a core
 * change (a distinct persistence-unavailable exception for those three seams);
 * tracked as a follow-up. Message-sniffing to reclassify is deliberately NOT
 * done — it is brittle across core message changes.
 */
final class FlowMutation
{
    /**
     * @param  callable(): (string|array{message: string, data?: array<string, mixed>})  $operation
     *                                                                                               Returns either a success message, or a `['message' => ..., 'data' => ...]` pair.
     */
    public static function run(callable $operation, int $successStatus = 200): JsonResponse
    {
        try {
            $result = $operation();
        } catch (FlowInputException $e) {
            return self::fail($e->getMessage(), 422);
        } catch (ApprovalPersistenceException $e) {
            self::logUnexpected($e);

            return self::fail('The approval store is unavailable. Try again later.', 503);
        } catch (FlowExecutionException $e) {
            return self::fail($e->getMessage(), 409);
        } catch (Throwable $e) {
            self::logUnexpected($e);

            return self::fail('Something went wrong. Try again.', 500);
        }

        $message = is_array($result) ? $result['message'] : $result;
        $data = is_array($result) ? ($result['data'] ?? []) : [];

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $successStatus);
    }

    private static function fail(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], $status);
    }

    private static function logUnexpected(Throwable $e): void
    {
        // Class only — an ApprovalPersistenceException/QueryException message
        // can interpolate bound values (and DB internals) into the SQL.
        Log::warning('laravel-flow-admin: a flow mutation failed unexpectedly', [
            'exception' => $e::class,
        ]);
    }
}
