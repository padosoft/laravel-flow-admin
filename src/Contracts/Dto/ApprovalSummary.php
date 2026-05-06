<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * One pending or recently-decided human-in-the-loop approval. The token
 * is hashed at rest by the upstream package; this DTO carries the token
 * id ONLY (never the plaintext approval token), so passing an instance
 * around the application can never leak the secret.
 */
final readonly class ApprovalSummary
{
    /**
     * @param  string  $tokenId  Stable identifier of the approval token (NOT the plaintext token).
     * @param  string  $status  Status slug (`pending` | `granted` | `rejected` | `expired`).
     * @param  ?string  $approver  Login of the principal that decided, null while pending.
     * @param  ?DateTimeImmutable  $decidedAt  Decision time, null while pending.
     */
    public function __construct(
        public string $tokenId,
        public string $runId,
        public string $stepName,
        public string $description,
        public string $status,
        public DateTimeImmutable $requestedAt,
        public ?string $approver,
        public ?DateTimeImmutable $decidedAt,
    ) {}
}
