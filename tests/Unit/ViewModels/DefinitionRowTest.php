<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\FlowDefinition;
use Padosoft\LaravelFlowAdmin\ViewModels\DefinitionRow;
use PHPUnit\Framework\TestCase;

final class DefinitionRowTest extends TestCase
{
    public function test_from_dto_renders_success_rate_label_and_keeps_ratio(): void
    {
        $dto = new FlowDefinition(
            name: 'order.fulfillment',
            version: 'v3.4',
            stepCount: 5,
            totalRuns: 1_240,
            successRate: 0.952,
        );

        $vm = DefinitionRow::fromDto($dto);

        $this->assertSame('order.fulfillment', $vm->name);
        $this->assertSame('v3.4', $vm->version);
        $this->assertSame(5, $vm->stepCount);
        $this->assertSame(1_240, $vm->totalRuns);
        $this->assertSame('95.2%', $vm->successRateLabel);
        $this->assertSame(0.952, $vm->successRateRatio);
    }

    public function test_zero_runs_renders_zero_percent_label(): void
    {
        $dto = new FlowDefinition(
            name: 'invoice.send',
            version: 'v1.0',
            stepCount: 3,
            totalRuns: 0,
            successRate: 0.0,
        );

        $vm = DefinitionRow::fromDto($dto);

        $this->assertSame('0%', $vm->successRateLabel);
        $this->assertSame(0.0, $vm->successRateRatio);
    }
}
