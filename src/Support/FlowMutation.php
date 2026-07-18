<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Padosoft\LaravelFlow\Exceptions\FlowExecutionException;
use Padosoft\LaravelFlow\Exceptions\FlowInputException;
use Padosoft\LaravelFlow\Exceptions\PersistenceUnavailableException;
use Throwable;

/**
 * Runs a core FlowEngine mutation and shapes its outcome into the admin's
 * uniform `{success, message, data}` JSON contract, mapping the engine's
 * typed exceptions to HTTP status codes so every mutation controller
 * (approve/reject, cancel, replay, redeliver) stays thin and consistent.
 *
 * Exception → status:
 *   - {@see FlowInputException}            → 422 (bad input, e.g. a blank token hash)
 *   - {@see PersistenceUnavailableException} → 503 (migrations missing / DB unreachable)
 *   - {@see FlowExecutionException}        → 409 (the action conflicts with the
 *                                             resource's current state: not found,
 *                                             already-decided, non-terminal, unpinned…)
 *   - any other {@see Throwable}           → 500 (sanitized; class-only log)
 *
 * The `FlowInputException`/`FlowExecutionException` messages are curated,
 * operator-facing `@api` strings that interpolate only ids already present in
 * the request URL (run id, flow name) — never secrets, and never a wrapped
 * cause (the engine passes causes via `previous:`, not into the message) — so
 * they are surfaced verbatim. The 503 and 500 paths use generic messages and
 * log the exception class only, never its message (which could carry DB/driver
 * internals).
 *
 * Persistence outages (tables not migrated / DB unreachable) raised by ANY seam
 * — approvals AND cancel/replay/redeliver — now surface as core's distinct
 * `PersistenceUnavailableException` (`ApprovalPersistenceException` is a subtype
 * of it), so this one catch maps every infrastructure outage to a retryable 503,
 * ahead of the 409 that its `FlowExecutionException` parent expresses for
 * ordinary state conflicts. (It must be caught BEFORE `FlowExecutionException`.)
 */
final class FlowMutation
{
    /**
     * @param  callable(): (string|array<string, mixed>)  $operation
     *                                                                Returns either a success message string,
     *                                                                or a `['message' => string, 'data' => array]`
     *                                                                pair. The array shape is validated at runtime
     *                                                                (a malformed payload fails closed with a 500)
     *                                                                rather than pinned in the type, so the guard
     *                                                                below is genuinely reachable.
     */
    public static function run(callable $operation, int $successStatus = 200): JsonResponse
    {
        try {
            $result = $operation();
        } catch (FlowInputException $e) {
            return self::fail($e->getMessage(), 422);
        } catch (PersistenceUnavailableException $e) {
            // Persistence outage (migrations missing / DB unreachable) from any
            // seam, incl. ApprovalPersistenceException (a subtype). Retryable →
            // 503. Caught BEFORE the FlowExecutionException parent. Logged with
            // a DISTINCT message (not logUnexpected's) so a handled infra outage
            // is separable from a genuine 500 in alerting/triage; class only —
            // the raw message can carry DB internals.
            Log::warning('laravel-flow-admin: a flow mutation hit a persistence outage', [
                'exception' => $e::class,
            ]);

            return self::fail('The flow store is unavailable. Try again later.', 503);
        } catch (FlowExecutionException $e) {
            return self::fail($e->getMessage(), 409);
        } catch (Throwable $e) {
            self::logUnexpected($e);

            return self::fail('Something went wrong. Try again.', 500);
        }

        if (is_array($result)) {
            $message = $result['message'] ?? null;
            $data = $result['data'] ?? [];

            // A callback that returns an array MUST carry a string `message`
            // and (if present) an array `data`. A malformed payload is a
            // programming error, not a client one — fail closed with a
            // sanitized 500 rather than letting an undefined-key access or a
            // type error escape the uniform envelope into a framework error page.
            if (! is_string($message) || ! is_array($data)) {
                Log::warning('laravel-flow-admin: a flow mutation returned a malformed success payload');

                return self::fail('Something went wrong. Try again.', 500);
            }
        } else {
            $message = $result;
            $data = [];
        }

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
