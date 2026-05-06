<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\ViewModels;

use Padosoft\LaravelFlowAdmin\Contracts\Dto\ThroughputBucket;

/**
 * One bar of the overview throughput chart. The numeric counts come
 * straight from the DTO; the View-model pre-computes the height
 * percentages relative to the largest total in the bucket series so
 * the Blade chart is render-only.
 *
 * Build a list with {@see fromSeries}, which runs the max-scaling pass
 * once across the whole series.
 */
final readonly class ThroughputBar
{
    public function __construct(
        public \DateTimeImmutable $at,
        public int $successCount,
        public int $failedCount,
        public float $successHeightRatio,
        public float $failedHeightRatio,
    ) {}

    /**
     * @param  list<ThroughputBucket>  $series
     * @return list<self>
     */
    public static function fromSeries(array $series): array
    {
        if ($series === []) {
            return [];
        }

        $max = 0;
        foreach ($series as $bucket) {
            $total = $bucket->successCount + $bucket->failedCount;
            if ($total > $max) {
                $max = $total;
            }
        }

        if ($max === 0) {
            // Series is all-zero — return bars with 0-height ratios so
            // the chart still renders the time axis.
            return array_map(
                static fn (ThroughputBucket $b): self => new self(
                    at: $b->at,
                    successCount: 0,
                    failedCount: 0,
                    successHeightRatio: 0.0,
                    failedHeightRatio: 0.0,
                ),
                $series,
            );
        }

        return array_map(
            static fn (ThroughputBucket $b): self => new self(
                at: $b->at,
                successCount: $b->successCount,
                failedCount: $b->failedCount,
                successHeightRatio: $b->successCount / $max,
                failedHeightRatio: $b->failedCount / $max,
            ),
            $series,
        );
    }
}
