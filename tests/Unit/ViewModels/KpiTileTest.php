<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\ViewModels\KpiTile;
use PHPUnit\Framework\TestCase;

final class KpiTileTest extends TestCase
{
    public function test_from_kpis_returns_four_tiles_in_design_order(): void
    {
        $kpis = new KpiSummary(
            totalRuns: 1_240,
            deltaTotalRuns: 42,
            successRate: 0.952,
            deltaSuccessRate: 0.012,
            failedRuns: 18,
            deltaFailedRuns: -3,
            avgDurationMs: 4_200,
            deltaAvgDurationMs: -150,
        );

        $tiles = KpiTile::fromKpis($kpis);

        $this->assertCount(4, $tiles);
        $this->assertSame(['Total Runs', 'Success Rate', 'Failed', 'Avg Duration'], array_map(
            static fn (KpiTile $t): string => $t->label,
            $tiles,
        ));
    }

    public function test_value_labels_are_pre_formatted(): void
    {
        $kpis = new KpiSummary(
            totalRuns: 1_240,
            deltaTotalRuns: 0,
            successRate: 0.952,
            deltaSuccessRate: 0.0,
            failedRuns: 18,
            deltaFailedRuns: 0,
            avgDurationMs: 4_200,
            p95DurationMs: 6_300,
            deltaAvgDurationMs: 0,
        );

        [$total, $success, $failed, $duration] = KpiTile::fromKpis($kpis);

        $this->assertSame('1240', $total->valueLabel);
        $this->assertSame('95.2%', $success->valueLabel);
        $this->assertSame('18', $failed->valueLabel);
        $this->assertSame('6.30s', $duration->valueLabel);
    }

    public function test_improvement_direction_inverts_for_failures_and_duration(): void
    {
        // Up = good for total/success, bad for failed/duration.
        // Verify the four signs are honoured correctly.
        $kpis = new KpiSummary(
            totalRuns: 100,
            deltaTotalRuns: 5,            // +5 → improvement
            successRate: 0.9,
            deltaSuccessRate: 0.02,       // up → improvement
            failedRuns: 3,
            deltaFailedRuns: 1,           // up → regression
            avgDurationMs: 1000,
            deltaAvgDurationMs: 100,      // slower → regression
        );

        [$total, $success, $failed, $duration] = KpiTile::fromKpis($kpis);

        $this->assertTrue($total->deltaIsImprovement);
        $this->assertTrue($success->deltaIsImprovement);
        $this->assertFalse($failed->deltaIsImprovement);
        $this->assertFalse($duration->deltaIsImprovement);
    }

    public function test_negative_delta_is_improvement_for_failed_and_duration(): void
    {
        $kpis = new KpiSummary(
            totalRuns: 100, deltaTotalRuns: 0,
            successRate: 0.9, deltaSuccessRate: 0.0,
            failedRuns: 3, deltaFailedRuns: -2,                // fewer failures → improvement
            avgDurationMs: 1000, deltaAvgDurationMs: -200,     // faster → improvement
        );

        [, , $failed, $duration] = KpiTile::fromKpis($kpis);

        $this->assertTrue($failed->deltaIsImprovement);
        $this->assertTrue($duration->deltaIsImprovement);
    }

    public function test_success_rate_delta_is_rendered_as_percentage_points(): void
    {
        $kpis = new KpiSummary(
            totalRuns: 100, deltaTotalRuns: 0,
            successRate: 0.9, deltaSuccessRate: 0.012,
            failedRuns: 0, deltaFailedRuns: 0,
            avgDurationMs: 0, deltaAvgDurationMs: 0,
        );

        [, $success] = KpiTile::fromKpis($kpis);

        $this->assertSame('+1pp', $success->deltaLabel);
    }
}
