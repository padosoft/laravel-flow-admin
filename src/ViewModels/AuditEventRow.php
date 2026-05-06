<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\AuditEvent;

/**
 * View-model wrapping a single audit event for the run-detail Audit
 * tab. Pre-encodes the payload to a pretty JSON string so the Blade
 * template can hand it directly to the JSON-highlight renderer
 * without re-serialising on every render.
 */
final readonly class AuditEventRow
{
    public function __construct(
        public \DateTimeImmutable $at,
        public string $type,
        public string $actor,
        public string $payloadJson,
    ) {}

    public static function fromDto(AuditEvent $dto): self
    {
        return new self(
            at: $dto->at,
            type: $dto->type,
            actor: $dto->actor,
            payloadJson: json_encode(
                $dto->payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ) ?: '{}',
        );
    }
}
