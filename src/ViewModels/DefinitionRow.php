<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\FlowDefinition;
use Padosoft\LaravelFlowAdmin\Support\Format;

final readonly class DefinitionRow
{
    public function __construct(
        public string $name,
        public string $version,
        public int $stepCount,
        public int $totalRuns,
        public string $successRateLabel,
        public float $successRateRatio,
    ) {}

    public static function fromDto(FlowDefinition $dto): self
    {
        return new self(
            name: $dto->name,
            version: $dto->version,
            stepCount: $dto->stepCount,
            totalRuns: $dto->totalRuns,
            successRateLabel: Format::percentLabel($dto->successRate),
            successRateRatio: $dto->successRate,
        );
    }
}
