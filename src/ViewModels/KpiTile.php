<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\KpiSummary;
use Padosoft\LaravelFlowAdmin\Support\Format;

/**
 * View-model for one of the four overview KPI tiles. Pre-formats every
 * label (value, delta, percentage) so the Blade tile is render-only.
 *
 * The factory {@see fromKpis} returns four tiles in design-source order
 * (Total Runs, Success Rate, Failed, Avg Duration) so a foreach in
 * Blade renders them deterministically.
 */
final readonly class KpiTile
{
    public function __construct(
        public string $label,
        public string $valueLabel,
        public string $deltaLabel,
        public bool $deltaIsImprovement,
    ) {}

    /**
     * @return list<self> Four tiles, fixed order.
     */
    public static function fromKpis(KpiSummary $kpis): array
    {
        return [
            new self(
                label: 'Total Runs',
                valueLabel: (string) $kpis->totalRuns,
                deltaLabel: Format::deltaLabel($kpis->deltaTotalRuns),
                deltaIsImprovement: $kpis->deltaTotalRuns >= 0,
            ),
            new self(
                label: 'Success Rate',
                valueLabel: Format::percentLabel($kpis->successRate),
                deltaLabel: Format::deltaLabel((int) round($kpis->deltaSuccessRate * 100)) . 'pp',
                deltaIsImprovement: $kpis->deltaSuccessRate >= 0,
            ),
            new self(
                label: 'Failed',
                valueLabel: (string) $kpis->failedRuns,
                deltaLabel: Format::deltaLabel($kpis->deltaFailedRuns),
                // Fewer failures is the improvement direction.
                deltaIsImprovement: $kpis->deltaFailedRuns <= 0,
            ),
            new self(
                label: 'Avg Duration',
                valueLabel: Format::durationLabel($kpis->avgDurationMs),
                deltaLabel: Format::deltaLabel($kpis->deltaAvgDurationMs) . 'ms',
                // Faster (lower) duration is the improvement direction.
                deltaIsImprovement: $kpis->deltaAvgDurationMs <= 0,
            ),
        ];
    }
}
