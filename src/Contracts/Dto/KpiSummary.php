<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

/**
 * Snapshot of the four headline numbers on the overview page (Total
 * Runs, Success Rate, Failed, Avg Duration). The `delta` fields carry
 * the period-over-period change so the Blade KPI tile can render the
 * up/down arrow without doing any maths in the view.
 */
final readonly class KpiSummary
{
    /**
     * @param  int  $totalRuns  Runs that started in the current period.
     * @param  int  $deltaTotalRuns  Difference vs the previous period (signed).
     * @param  float  $successRate  Ratio in [0.0, 1.0] — the view multiplies by 100 for display.
     * @param  float  $deltaSuccessRate  Period-over-period change of the ratio (signed, in [−1.0, 1.0]).
     * @param  int  $failedRuns  Runs that ended in `failed` (excludes `compensated`).
     * @param  int  $deltaFailedRuns  Period-over-period change (signed).
     * @param  int  $avgDurationMs  Mean run duration in ms over the period.
     * @param  int  $deltaAvgDurationMs  Period-over-period change in ms (signed).
     */
    public function __construct(
        public int $totalRuns,
        public int $deltaTotalRuns,
        public float $successRate,
        public float $deltaSuccessRate,
        public int $failedRuns,
        public int $deltaFailedRuns,
        public int $avgDurationMs,
        public int $deltaAvgDurationMs,
    ) {}
}
