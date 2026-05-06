<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * Read-only snapshot of a single run for the runs-list, recent-runs, and
 * recent-failures widgets. Maps 1:1 to one row in the design source's
 * mock data (`.design-source/project/data.jsx`) and one row of the
 * runs table the consumer's read-model returns.
 *
 * Public extension surface: consumers who write a custom adapter MUST
 * return instances of this final readonly class. It is part of the
 * package's public contract from v0.1.0 forward — adding fields requires
 * a SemVer minor; renaming or removing fields requires a SemVer major.
 */
final readonly class RunSummary
{
    /**
     * @param  string  $id  Globally-unique run identifier (UUID, ULID, or vendor-specific stable string).
     * @param  string  $flowName  Human-readable flow definition name, e.g. `order.fulfillment`.
     * @param  string  $flowVersion  Definition version string, e.g. `v3.4`.
     * @param  string  $status  Status slug (`running` | `success` | `failed` | `paused` | `pending` | `compensated`).
     * @param  string  $actor  Identifier of the principal that started the run.
     * @param  string  $correlationId  External correlation/trace key, displayed verbatim — no PII normalisation here.
     * @param  DateTimeImmutable  $startedAt  When the run entered `running`.
     * @param  ?DateTimeImmutable  $finishedAt  When the run reached a terminal state, null while in-flight.
     * @param  ?int  $durationMs  Total wall-clock duration in ms; null if still running.
     * @param  int  $stepCount  Total registered steps for this run (NOT remaining).
     * @param  int  $attemptsTotal  Sum of attempts across all steps (a step retried 3 times contributes 3).
     */
    public function __construct(
        public string $id,
        public string $flowName,
        public string $flowVersion,
        public string $status,
        public string $actor,
        public string $correlationId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?int $durationMs,
        public int $stepCount,
        public int $attemptsTotal,
    ) {}
}
