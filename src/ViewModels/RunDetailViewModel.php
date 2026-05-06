<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;

/**
 * Composite view-model backing the run detail page. Wraps a
 * {@see RunRow} for the page header + a list of {@see StepRow} for
 * the timeline/gantt/dag + a list of {@see AuditEventRow} for the
 * audit tab + the input/output JSON strings the JSON-highlight
 * pipeline renders directly.
 */
final readonly class RunDetailViewModel
{
    /**
     * @param  list<StepRow>  $steps
     * @param  list<AuditEventRow>  $audit
     */
    public function __construct(
        public RunRow $summary,
        public array $steps,
        public array $audit,
        public string $inputJson,
        public string $outputJson,
    ) {}

    public static function fromDto(RunDetail $dto): self
    {
        return new self(
            summary: RunRow::fromDto($dto->summary),
            steps: array_map(StepRow::fromDto(...), $dto->steps),
            audit: array_map(AuditEventRow::fromDto(...), $dto->audit),
            inputJson: self::encode($dto->inputPayload),
            outputJson: self::encode($dto->outputPayload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) ?: '{}';
    }
}
