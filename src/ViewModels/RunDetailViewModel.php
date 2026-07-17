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
     * Public run-status slugs that are still ACTIVE (non-terminal). Cancel is
     * offered only for these; replay only for their complement (any terminal
     * state). Keyed on the admin's public slug (see EloquentReadModel::
     * toPublicStatus) — deliberately an active-set allowlist so any present
     * or future terminal slug (success/failed/compensated/aborted/…) is
     * replayable without re-enumerating them here.
     */
    private const ACTIVE_RUN_STATUSES = ['running', 'paused', 'pending'];

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

    /**
     * Whether the Cancel action applies: only while the run is still active.
     * `Flow::cancel()` is idempotent on an already-terminal run, but there is
     * nothing to cancel, so the button is hidden.
     */
    public function canCancel(): bool
    {
        return in_array($this->summary->status, self::ACTIVE_RUN_STATUSES, true);
    }

    /**
     * Whether the Replay action applies: only once the run is terminal.
     * `Flow::replay()` rejects a non-terminal run, and may still reject a
     * terminal one that isn't a pinned graph run (surfaced as a 409 toast) —
     * the view cannot see the pin, so it offers replay for every terminal run
     * and lets the seam have the final say.
     */
    public function canReplay(): bool
    {
        return $this->summary->status !== ''
            && ! in_array($this->summary->status, self::ACTIVE_RUN_STATUSES, true);
    }

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
