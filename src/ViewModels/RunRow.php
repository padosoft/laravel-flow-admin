<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Support\Format;

/**
 * View-model adapting a {@see RunSummary} DTO to the shape the runs
 * table / recent-runs widget Blade templates render. Adds:
 *   - `durationLabel`: pre-formatted `12.4s` / `2.3m` / `—`
 *   - `statusLabel`: human-readable status text (`Succeeded`, `Failed`, …)
 *
 * Final readonly so the view layer cannot mutate state — the only
 * supported construction path is {@see fromDto}.
 */
final readonly class RunRow
{
    public function __construct(
        public string $id,
        public string $flowName,
        public string $flowVersion,
        public string $status,
        public string $statusLabel,
        public string $actor,
        public string $correlationId,
        public string $durationLabel,
        public int $stepCount,
        public int $attemptsTotal,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {}

    public static function fromDto(RunSummary $dto): self
    {
        return new self(
            id: $dto->id,
            flowName: $dto->flowName,
            flowVersion: $dto->flowVersion,
            status: $dto->status,
            statusLabel: Format::statusLabel($dto->status),
            actor: $dto->actor,
            correlationId: $dto->correlationId,
            durationLabel: Format::durationLabel($dto->durationMs),
            stepCount: $dto->stepCount,
            attemptsTotal: $dto->attemptsTotal,
            startedAt: $dto->startedAt,
            finishedAt: $dto->finishedAt,
        );
    }
}
