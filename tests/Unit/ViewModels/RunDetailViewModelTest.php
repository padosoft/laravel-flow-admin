<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\AuditEvent;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunDetail;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\Step;
use Padosoft\LaravelFlowAdmin\ViewModels\RunDetailViewModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunDetailViewModelTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function actionEligibilityProvider(): array
    {
        // [publicStatus, canCancel, canReplay] — cancel only while active,
        // replay only once terminal (any non-active, non-empty status).
        return [
            'running is cancellable, not replayable' => ['running', true, false],
            'paused is cancellable, not replayable' => ['paused', true, false],
            'pending is cancellable, not replayable' => ['pending', true, false],
            'success is replayable, not cancellable' => ['success', false, true],
            'failed is replayable, not cancellable' => ['failed', false, true],
            'compensated is replayable, not cancellable' => ['compensated', false, true],
            'empty status offers neither' => ['', false, false],
        ];
    }

    #[DataProvider('actionEligibilityProvider')]
    public function test_cancel_and_replay_eligibility_follows_run_status(string $status, bool $canCancel, bool $canReplay): void
    {
        $vm = $this->viewModelWithStatus($status);

        $this->assertSame($canCancel, $vm->canCancel());
        $this->assertSame($canReplay, $vm->canReplay());
    }

    private function viewModelWithStatus(string $status): RunDetailViewModel
    {
        $started = new DateTimeImmutable('2026-05-06T10:00:00Z');

        return RunDetailViewModel::fromDto(new RunDetail(
            summary: new RunSummary(
                id: 'r', flowName: 'f', flowVersion: 'v',
                status: $status, actor: 'a', correlationId: 'c',
                startedAt: $started, finishedAt: null, durationMs: null,
                stepCount: 0, attemptsTotal: 0,
            ),
            steps: [],
            audit: [],
            inputPayload: [],
            outputPayload: [],
        ));
    }

    public function test_from_dto_composes_summary_steps_audit_and_payloads(): void
    {
        $started = new DateTimeImmutable('2026-05-06T10:00:00Z');

        $summary = new RunSummary(
            id: 'run_x',
            flowName: 'order.fulfillment',
            flowVersion: 'v3.4',
            status: 'success',
            actor: 'a@example.test',
            correlationId: 'c',
            startedAt: $started,
            finishedAt: $started->modify('+5s'),
            durationMs: 5_000,
            stepCount: 2,
            attemptsTotal: 2,
        );

        $steps = [
            new Step(
                name: 'charge',
                status: 'success',
                startedAt: $started,
                finishedAt: $started->modify('+2s'),
                durationMs: 2_000,
                attempts: 1,
                dependsOn: [],
                errorMessage: null,
            ),
            new Step(
                name: 'send_receipt',
                status: 'success',
                startedAt: $started->modify('+2s'),
                finishedAt: $started->modify('+5s'),
                durationMs: 3_000,
                attempts: 1,
                dependsOn: ['charge'],
                errorMessage: null,
            ),
        ];

        $audit = [
            new AuditEvent(
                at: $started,
                type: 'run.started',
                actor: 'a@example.test',
                payload: ['source' => 'http'],
            ),
        ];

        $detail = new RunDetail(
            summary: $summary,
            steps: $steps,
            audit: $audit,
            inputPayload: ['order_id' => 42],
            outputPayload: ['receipt_id' => 'r_1'],
        );

        $vm = RunDetailViewModel::fromDto($detail);

        // Summary maps to a RunRow with pre-formatted labels.
        $this->assertSame('run_x', $vm->summary->id);
        $this->assertSame('Succeeded', $vm->summary->statusLabel);
        $this->assertSame('5.00s', $vm->summary->durationLabel);

        // Steps map to StepRow array preserving order + dependsOn.
        $this->assertCount(2, $vm->steps);
        $this->assertSame('charge', $vm->steps[0]->name);
        $this->assertSame('Succeeded', $vm->steps[0]->statusLabel);
        $this->assertSame('2.00s', $vm->steps[0]->durationLabel);
        $this->assertSame(['charge'], $vm->steps[1]->dependsOn);

        // Audit gets pretty-printed JSON in payloadJson.
        $this->assertCount(1, $vm->audit);
        $this->assertSame('run.started', $vm->audit[0]->type);
        $this->assertStringContainsString('"source": "http"', $vm->audit[0]->payloadJson);

        // Input/output payloads are pretty JSON strings.
        $this->assertStringContainsString('"order_id": 42', $vm->inputJson);
        $this->assertStringContainsString('"receipt_id": "r_1"', $vm->outputJson);
    }

    public function test_empty_payloads_serialise_to_object_braces(): void
    {
        $started = new DateTimeImmutable('2026-05-06T10:00:00Z');
        $summary = new RunSummary(
            id: 'r', flowName: 'f', flowVersion: 'v',
            status: 'running', actor: 'a', correlationId: 'c',
            startedAt: $started, finishedAt: null, durationMs: null,
            stepCount: 0, attemptsTotal: 0,
        );

        $vm = RunDetailViewModel::fromDto(new RunDetail(
            summary: $summary,
            steps: [],
            audit: [],
            inputPayload: [],
            outputPayload: [],
        ));

        // PHP `json_encode([])` gives `[]`, not `{}` — that's expected
        // when the source is an empty array.
        $this->assertSame('[]', $vm->inputJson);
        $this->assertSame('[]', $vm->outputJson);
        $this->assertSame([], $vm->steps);
        $this->assertSame([], $vm->audit);
    }
}
