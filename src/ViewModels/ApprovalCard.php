<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\Support\Format;

/**
 * View-model for a single approval card on the overview's
 * "Pending approvals" widget and the dedicated approvals page.
 */
final readonly class ApprovalCard
{
    public function __construct(
        public string $tokenId,
        public string $runId,
        public string $stepName,
        public string $description,
        public string $status,
        public string $statusLabel,
        public bool $isPending,
        public ?string $approver,
        public \DateTimeImmutable $requestedAt,
        public ?\DateTimeImmutable $decidedAt,
        // SHA-256 token hash the approve/reject actions post to the server;
        // null when unknown (a pending row with no hash cannot be acted on).
        public ?string $tokenHash = null,
    ) {}

    /**
     * Whether the approve/reject action buttons can be wired for this card:
     * only while the approval is still pending AND the adapter surfaced a
     * token hash (the key `Flow::resumeByHash()`/`rejectByHash()` require).
     */
    public function canDecide(): bool
    {
        return $this->isPending && $this->tokenHash !== null && $this->tokenHash !== '';
    }

    public static function fromDto(ApprovalSummary $dto): self
    {
        return new self(
            tokenId: $dto->tokenId,
            runId: $dto->runId,
            stepName: $dto->stepName,
            description: $dto->description,
            status: $dto->status,
            statusLabel: Format::statusLabel($dto->status),
            isPending: $dto->status === 'pending',
            approver: $dto->approver,
            requestedAt: $dto->requestedAt,
            decidedAt: $dto->decidedAt,
            tokenHash: $dto->tokenHash,
        );
    }
}
