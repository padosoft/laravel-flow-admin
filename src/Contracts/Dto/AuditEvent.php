<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * Single audit event in a run's timeline. The package never re-renders
 * `payload` — it is passed through as-is to the JSON-highlight pipeline,
 * so adapters must ensure secrets/PII have been redacted upstream
 * (the package does NOT redact).
 */
final readonly class AuditEvent
{
    /**
     * @param  string  $type  Event slug (`run.started`, `step.failed`, `approval.granted`, …).
     * @param  string  $actor  Identifier of the principal that emitted the event.
     * @param  array<string|int, mixed>  $payload  Free-form payload, MUST be JSON-serialisable.
     */
    public function __construct(
        public DateTimeImmutable $at,
        public string $type,
        public string $actor,
        public array $payload,
    ) {}
}
