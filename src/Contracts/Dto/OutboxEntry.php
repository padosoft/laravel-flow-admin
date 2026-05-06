<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * One row of the webhook-outbox table. Carries enough context to render
 * the row + Retry button without extra round-trips. `lastError` is
 * sanitised at the source (no stack trace, no auth headers).
 */
final readonly class OutboxEntry
{
    /**
     * @param  string  $eventType  Event slug (`run.succeeded`, `approval.requested`, …).
     * @param  string  $destination  Target URL or queue name, displayed verbatim.
     * @param  string  $status  Status slug (`pending` | `delivered` | `failed` | `dead`).
     * @param  int  $attempts  Delivery attempts so far.
     * @param  ?DateTimeImmutable  $nextAttemptAt  Scheduled retry time, null if terminal.
     * @param  ?string  $lastError  Last delivery error (sanitised, no headers/secrets).
     */
    public function __construct(
        public string $id,
        public string $eventType,
        public string $destination,
        public string $status,
        public int $attempts,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
    ) {}
}
