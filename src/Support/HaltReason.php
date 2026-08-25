<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

use Padosoft\LaravelFlow\Dashboard\StepSummary;

/**
 * Turns the exception class the engine stamped on a step into "why it stopped".
 *
 * `failed` is one status covering two unrelated events. A step whose delegation
 * grant was revoked mid-run did exactly what it was built to do — a person
 * withdrew permission and the loop halted before the next tool call. A step
 * that threw a `PDOException` is broken. Rendering both as a red badge sends an
 * operator to debug the first one and lets the second sit in a queue of "known
 * policy stops".
 *
 * The message cannot make that distinction: it is redacted at the source and
 * says nothing structural. The class can, and the engine already records it —
 * {@see StepSummary::$errorClass}.
 *
 * Two `kind`s, deliberately, not five:
 *
 *  - **`policy`** — a rule the operator configured stopped this on purpose.
 *    Nothing is broken; the question is whether the rule is right.
 *  - **`error`** — something failed. The question is what.
 *
 * The map is matched on the **class basename**, so a host application that
 * subclasses one of these keeps the classification, and `laravel-flow-ai`
 * moving a class between namespaces does not silently reclassify a halt as a
 * crash. An unknown class is an `error`: the safe default is "look at this",
 * never "a rule handled it".
 */
final class HaltReason
{
    /**
     * Basename => [label, kind]. The policy entries are the exceptions
     * `laravel-flow-ai` throws when one of its own bounds is reached.
     *
     * @var array<string, array{string, string}>
     */
    private const REASONS = [
        // The delegation grant behind this run was revoked or expired. The node
        // re-checks before every tool call, so this is a stop, not a failure.
        'GrantRevokedException' => ['Delegation revoked', 'policy'],
        // The agent asked for a tool outside its allowlist. Deny-by-default.
        'AgentToolNotAllowedException' => ['Tool not allowed', 'policy'],
        // Iterations, tokens or cost ceiling reached.
        'AgentBudgetExhaustedException' => ['Budget exhausted', 'policy'],
        // A guardrail refused the call.
        'PolicyDeniedException' => ['Blocked by policy', 'policy'],
        // The spend meter for the delegated identity said no.
        'DelegationBudgetExceededException' => ['Delegation budget spent', 'policy'],

        // Failures worth naming, because "failed" alone sends the operator to
        // the wrong layer: these are the MCP transport, not the flow.
        'McpConnectionException' => ['Tool server unreachable', 'error'],
        'McpToolExecutionException' => ['Tool threw', 'error'],
    ];

    /**
     * A short human label, or null when there is nothing to explain.
     */
    public static function label(?string $errorClass): ?string
    {
        $reason = self::lookup($errorClass);

        if ($reason !== null) {
            return $reason[0];
        }

        // Unknown class: the basename is still better than nothing — an
        // operator recognises `PDOException` faster than they recognise a red
        // dot — so it is shown as-is rather than flattened to "Failed".
        return self::basename($errorClass);
    }

    /**
     * `policy` when a configured rule stopped the step, `error` otherwise.
     */
    public static function kind(?string $errorClass): ?string
    {
        if ($errorClass === null || $errorClass === '') {
            return null;
        }

        $reason = self::lookup($errorClass);

        return $reason === null ? 'error' : $reason[1];
    }

    /**
     * @return array{string, string}|null
     */
    private static function lookup(?string $errorClass): ?array
    {
        $basename = self::basename($errorClass);

        return $basename === null ? null : (self::REASONS[$basename] ?? null);
    }

    private static function basename(?string $errorClass): ?string
    {
        if ($errorClass === null || $errorClass === '') {
            return null;
        }

        $position = strrpos($errorClass, '\\');

        $basename = $position === false ? $errorClass : substr($errorClass, $position + 1);

        return $basename === '' ? null : $basename;
    }
}
