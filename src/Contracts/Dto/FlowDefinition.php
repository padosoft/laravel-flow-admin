<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

/**
 * Registered flow definition snapshot for the definitions page. The
 * `successRate` field powers the success-rate progress bar — adapters
 * should compute it over a sensible recent window (last 30 days is the
 * default in the design source).
 */
final readonly class FlowDefinition
{
    /**
     * @param  string  $name  Flow definition slug, e.g. `order.fulfillment`.
     * @param  string  $version  Version string, e.g. `v3.4`.
     * @param  int  $stepCount  Number of declared steps.
     * @param  int  $totalRuns  Run count over the success-rate window.
     * @param  float  $successRate  Ratio in [0.0, 1.0].
     */
    public function __construct(
        public string $name,
        public string $version,
        public int $stepCount,
        public int $totalRuns,
        public float $successRate,
    ) {}
}
