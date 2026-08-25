<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\Support\Format;
use Padosoft\LaravelFlowAdmin\Support\HaltReason;

/**
 * View-model for one step inside the run detail timeline / gantt / dag.
 * Adds the same `statusLabel` and `durationLabel` pre-formatting as
 * {@see RunRow} so Blade templates do no arithmetic.
 */
final readonly class StepRow
{
    public function __construct(
        public string $name,
        public string $status,
        public string $statusLabel,
        public string $durationLabel,
        public int $attempts,
        /** @var list<string> */
        public array $dependsOn,
        public ?string $errorMessage,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public ?string $errorClass = null,
        /** Human label for why the step stopped, or null when it did not stop badly. */
        public ?string $haltLabel = null,
        /** `policy` when a rule stopped the step on purpose, `error` when something broke. */
        public ?string $haltKind = null,
    ) {}

    public static function fromDto(Step $dto): self
    {
        return new self(
            name: $dto->name,
            status: $dto->status,
            statusLabel: Format::statusLabel($dto->status),
            durationLabel: Format::durationLabel($dto->durationMs),
            attempts: $dto->attempts,
            dependsOn: $dto->dependsOn,
            errorMessage: $dto->errorMessage,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
            errorClass: $dto->errorClass,
            haltLabel: HaltReason::label($dto->errorClass),
            haltKind: HaltReason::kind($dto->errorClass),
        );
    }
}
