<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ApprovalSummary;
use Padosoft\LaravelFlowAdmin\ViewModels\ApprovalCard;
use PHPUnit\Framework\TestCase;

final class ApprovalCardTest extends TestCase
{
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

    public function test_can_decide_only_when_pending_and_a_token_hash_is_present(): void
    {
        $vm = ApprovalCard::fromDto($this->pending(tokenHash: 'a1b2c3'));

        $this->assertSame('a1b2c3', $vm->tokenHash);
        $this->assertTrue($vm->canDecide());
    }

    public function test_cannot_decide_a_pending_approval_without_a_token_hash(): void
    {
        $vm = ApprovalCard::fromDto($this->pending(tokenHash: null));

        $this->assertNull($vm->tokenHash);
        $this->assertFalse($vm->canDecide());
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
