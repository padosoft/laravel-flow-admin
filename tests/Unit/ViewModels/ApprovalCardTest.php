<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\ViewModels\ApprovalCard;
use PHPUnit\Framework\TestCase;

final class ApprovalCardTest extends TestCase
{
    // A well-formed SHA-256 hex digest (64 hex chars), matching the mutation
    // routes' {tokenHash} constraint.
    private const VALID_HASH = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2';

    public function test_pending_approval_marks_is_pending_and_carries_no_decision(): void
    {
        $dto = new ApprovalSummary(
            tokenId: 'tok_42',
            runId: 'run_x',
            stepName: 'manager_review',
            description: 'Approve refund > $500',
            status: 'pending',
            requestedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            approver: null,
            decidedAt: null,
        );

        $vm = ApprovalCard::fromDto($dto);

        $this->assertTrue($vm->isPending);
        $this->assertSame('Pending', $vm->statusLabel);
        $this->assertNull($vm->approver);
        $this->assertNull($vm->decidedAt);
    }

    public function test_decided_approval_carries_approver_and_decision_time(): void
    {
        $decidedAt = new DateTimeImmutable('2026-05-06T10:05:00Z');

        $dto = new ApprovalSummary(
            tokenId: 'tok_x',
            runId: 'run_y',
            stepName: 'auditor_signoff',
            description: '',
            status: 'granted',
            requestedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            approver: 'auditor@example.test',
            decidedAt: $decidedAt,
        );

        $vm = ApprovalCard::fromDto($dto);

        $this->assertFalse($vm->isPending);
        $this->assertSame('Granted', $vm->statusLabel);
        $this->assertSame('auditor@example.test', $vm->approver);
        $this->assertSame($decidedAt, $vm->decidedAt);
    }

    public function test_can_decide_only_when_pending_and_a_wellformed_token_hash_is_present(): void
    {
        $vm = ApprovalCard::fromDto($this->pending(tokenHash: self::VALID_HASH));

        $this->assertSame(self::VALID_HASH, $vm->tokenHash);
        $this->assertTrue($vm->canDecide());
    }

    public function test_cannot_decide_a_pending_approval_without_a_token_hash(): void
    {
        $vm = ApprovalCard::fromDto($this->pending(tokenHash: null));

        $this->assertNull($vm->tokenHash);
        $this->assertFalse($vm->canDecide());
    }

    public function test_cannot_decide_a_pending_approval_with_a_malformed_token_hash(): void
    {
        // A short / non-64-hex hash would POST to a URL the [A-Fa-f0-9]{64}
        // route rejects (404), so the buttons must not render.
        foreach (['a1b2c3', str_repeat('z', 64), str_repeat('a', 63), str_repeat('a', 65)] as $bad) {
            $vm = ApprovalCard::fromDto($this->pending(tokenHash: $bad));
            $this->assertFalse($vm->canDecide(), "canDecide must be false for hash [{$bad}]");
        }
    }

    public function test_cannot_decide_an_already_decided_approval(): void
    {
        $dto = new ApprovalSummary(
            tokenId: 'tok_x',
            runId: 'run_y',
            stepName: 'auditor_signoff',
            description: '',
            status: 'granted',
            requestedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            approver: 'auditor@example.test',
            decidedAt: new DateTimeImmutable('2026-05-06T10:05:00Z'),
            tokenHash: 'a1b2c3',
        );

        $this->assertFalse(ApprovalCard::fromDto($dto)->canDecide());
    }

    private function pending(?string $tokenHash): ApprovalSummary
    {
        return new ApprovalSummary(
            tokenId: 'tok_42',
            runId: 'run_x',
            stepName: 'manager_review',
            description: 'Approve refund > $500',
            status: 'pending',
            requestedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            approver: null,
            decidedAt: null,
            tokenHash: $tokenHash,
        );
    }
}
