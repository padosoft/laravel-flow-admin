<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

/**
 * Full run shape backing the run-detail page (`/flow/runs/{id}`).
 *
 * Composes a {@see RunSummary} (the row-shape data) with the
 * step-by-step breakdown ({@see Step}), the audit timeline
 * ({@see AuditEvent}), and the verbatim input/output payloads.
 *
 * `inputPayload` and `outputPayload` are passed straight to the
 * JSON-highlight pipeline; adapters MUST redact secrets / PII before
 * returning a RunDetail. The package's UI surfaces these payloads
 * unchanged.
 */
final readonly class RunDetail
{
    /**
     * @param  list<Step>  $steps  Steps in declaration order.
     * @param  list<AuditEvent>  $audit  Audit events in chronological order.
     * @param  array<string, mixed>  $inputPayload  Run input, JSON-serialisable.
     * @param  array<string, mixed>  $outputPayload  Run output, JSON-serialisable; empty array while in-flight.
     */
    public function __construct(
        public RunSummary $summary,
        public array $steps,
        public array $audit,
        public array $inputPayload,
        public array $outputPayload,
    ) {}
}
