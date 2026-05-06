<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\ViewModels\RunRow;
use PHPUnit\Framework\TestCase;

final class RunRowTest extends TestCase
{
    public function test_from_dto_maps_every_field_and_pre_formats_labels(): void
    {
        $started = new DateTimeImmutable('2026-05-06T10:00:00Z');
        $finished = new DateTimeImmutable('2026-05-06T10:00:12.4Z');

        $dto = new RunSummary(
            id: 'run_01HZAB',
            flowName: 'order.fulfillment',
            flowVersion: 'v3.4',
            status: 'success',
            actor: 'service@example.test',
            correlationId: 'corr_42',
            startedAt: $started,
            finishedAt: $finished,
            durationMs: 12_400,
            stepCount: 5,
            attemptsTotal: 6,
        );

        $vm = RunRow::fromDto($dto);

        $this->assertSame('run_01HZAB', $vm->id);
        $this->assertSame('order.fulfillment', $vm->flowName);
        $this->assertSame('v3.4', $vm->flowVersion);
        $this->assertSame('success', $vm->status);
        $this->assertSame('Succeeded', $vm->statusLabel);
        $this->assertSame('service@example.test', $vm->actor);
        $this->assertSame('corr_42', $vm->correlationId);
        $this->assertSame('12.40s', $vm->durationLabel);
        $this->assertSame(5, $vm->stepCount);
        $this->assertSame(6, $vm->attemptsTotal);
        $this->assertSame($started, $vm->startedAt);
        $this->assertSame($finished, $vm->finishedAt);
    }

    public function test_in_flight_run_has_em_dash_duration_and_null_finished_at(): void
    {
        $dto = new RunSummary(
            id: 'run_inflight',
            flowName: 'invoice.send',
            flowVersion: 'v1.0',
            status: 'running',
            actor: 'user@example.test',
            correlationId: 'corr_1',
            startedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            finishedAt: null,
            durationMs: null,
            stepCount: 3,
            attemptsTotal: 1,
        );

        $vm = RunRow::fromDto($dto);

        $this->assertNull($vm->finishedAt);
        $this->assertSame('—', $vm->durationLabel);
        $this->assertSame('Running', $vm->statusLabel);
    }

    public function test_unknown_status_falls_through_verbatim(): void
    {
        $dto = new RunSummary(
            id: 'run_x',
            flowName: 'flow',
            flowVersion: 'v1',
            status: 'frobbed',
            actor: 'a@example.test',
            correlationId: 'c',
            startedAt: new DateTimeImmutable('2026-05-06T10:00:00Z'),
            finishedAt: null,
            durationMs: null,
            stepCount: 0,
            attemptsTotal: 0,
        );

        $this->assertSame('frobbed', RunRow::fromDto($dto)->statusLabel);
    }
}
