<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\ViewModels\StepRow;
use PHPUnit\Framework\TestCase;

final class StepRowTest extends TestCase
{
    public function test_a_revoked_delegation_reads_as_a_policy_stop_not_a_crash(): void
    {
        $row = StepRow::fromDto($this->step(
            errorClass: 'Padosoft\LaravelFlowAI\Identity\Exceptions\GrantRevokedException',
            errorMessage: 'Delegation grant dgr_01J9 was revoked.',
        ));

        // Same `failed` status as a crash — the label and kind are the only
        // thing telling an operator not to go debugging.
        $this->assertSame('failed', $row->status);
        $this->assertSame('Delegation revoked', $row->haltLabel);
        $this->assertSame('policy', $row->haltKind);
        $this->assertSame('Delegation grant dgr_01J9 was revoked.', $row->errorMessage);
    }

    public function test_an_unrecognised_exception_stays_an_error_and_keeps_its_name(): void
    {
        $row = StepRow::fromDto($this->step(errorClass: 'PDOException'));

        $this->assertSame('PDOException', $row->haltLabel);
        $this->assertSame('error', $row->haltKind);
    }

    public function test_a_succeeded_step_has_nothing_to_explain(): void
    {
        $row = StepRow::fromDto($this->step(status: 'success', errorClass: null, errorMessage: null));

        $this->assertNull($row->haltLabel);
        $this->assertNull($row->haltKind);
    }

    public function test_a_step_from_a_reader_that_never_sets_the_class_still_maps(): void
    {
        // `errorClass` is the last constructor parameter and defaults to null,
        // so an adapter built before this field existed keeps working.
        $dto = new Step(
            name: 'charge_card',
            status: 'failed',
            startedAt: new DateTimeImmutable('2026-08-25T10:00:00+00:00'),
            finishedAt: new DateTimeImmutable('2026-08-25T10:00:01+00:00'),
            durationMs: 1000,
            attempts: 1,
            dependsOn: [],
            errorMessage: 'boom',
        );

        $row = StepRow::fromDto($dto);

        $this->assertNull($row->errorClass);
        $this->assertNull($row->haltLabel);
        $this->assertNull($row->haltKind);
    }

    private function step(
        string $status = 'failed',
        ?string $errorClass = null,
        ?string $errorMessage = 'something went wrong',
    ): Step {
        return new Step(
            name: 'agent_step',
            status: $status,
            startedAt: new DateTimeImmutable('2026-08-25T10:00:00+00:00'),
            finishedAt: new DateTimeImmutable('2026-08-25T10:00:02+00:00'),
            durationMs: 2000,
            attempts: 1,
            dependsOn: [],
            errorMessage: $errorMessage,
            cacheHit: false,
            errorClass: $errorClass,
        );
    }
}
