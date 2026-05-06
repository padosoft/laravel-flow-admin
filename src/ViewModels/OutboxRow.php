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
            attempts: $dto->attempts,
            nextAttemptAt: $dto->nextAttemptAt,
            lastError: $dto->lastError,
        );
    }
}
