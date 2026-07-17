<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\OutboxEntry;
use Padosoft\LaravelFlowAdmin\Support\Format;

final readonly class OutboxRow
{
    public function __construct(
        public string $id,
        public string $eventType,
        public string $destination,
        public string $status,
        public string $statusLabel,
        public bool $canRetry,
        public bool $canRedeliver,
        public int $attempts,
        public ?\DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
    ) {}

    public static function fromDto(OutboxEntry $dto): self
    {
        return new self(
            id: $dto->id,
            eventType: $dto->eventType,
            destination: $dto->destination,
            status: $dto->status,
            statusLabel: Format::statusLabel($dto->status),
            // `delivered` is terminal; only `pending`, `failed`, `dead`
            // expose the Retry button. `dead` runs trigger a confirm
            // step (handled by the page partial in Macro 7).
            canRetry: in_array($dto->status, ['pending', 'failed', 'dead'], true),
            // The redeliver ACTION (Flow::redeliverWebhook) only requeues a
            // `failed` row (its CAS is failed→pending); a `pending` row is
            // already queued and a `delivered` one is terminal, so the button
            // is offered only where the seam will actually act — narrower than
            // `canRetry`, which is the broader visual "retry-eligible" hint.
            canRedeliver: $dto->status === 'failed',
            attempts: $dto->attempts,
            nextAttemptAt: $dto->nextAttemptAt,
            lastError: $dto->lastError,
        );
    }
}
