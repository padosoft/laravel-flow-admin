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
}
