<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * One step inside a run. Consumed by the run-detail timeline / gantt /
 * dag visualisations. The `dependsOn` list lets the DAG renderer build
 * its edges without reading the underlying flow definition.
 */
final readonly class Step
{
    /**
     * @param  string  $name  Step slug (`charge_card`, `send_receipt`, …).
     * @param  string  $status  Status slug (`pending` | `running` | `success` | `failed` | `compensated`).
     * @param  ?DateTimeImmutable  $startedAt  First-attempt start time; null while pending.
     * @param  ?DateTimeImmutable  $finishedAt  Terminal-state time; null while running.
     * @param  ?int  $durationMs  Wall-clock between start and finish; null while pending or running.
     * @param  int  $attempts  How many times this step was tried (1 = no retries).
     * @param  list<string>  $dependsOn  Step names that must succeed before this one runs.
     * @param  ?string  $errorMessage  Last error message, sanitised at the source (no stack trace, no secrets).
     */
    public function __construct(
        public string $name,
        public string $status,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?int $durationMs,
        public int $attempts,
        public array $dependsOn,
        public ?string $errorMessage,
    ) {}
}
