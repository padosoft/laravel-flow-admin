<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts\Dto;

use DateTimeImmutable;

/**
 * One time bucket of the overview throughput chart. The chart renders
 * `successCount` and `failedCount` as a stacked bar over `at`; the
 * adapter is responsible for choosing the bucket width (5m / 15m / 1h)
 * and aligning timestamps to bucket boundaries.
 */
final readonly class ThroughputBucket
{
    public function __construct(
        public DateTimeImmutable $at,
        public int $successCount,
        public int $failedCount,
    ) {}
}
