<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\ViewModels;

use DateTimeImmutable;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\ThroughputBucket;
use Padosoft\LaravelFlowAdmin\ViewModels\ThroughputBar;
use PHPUnit\Framework\TestCase;

final class ThroughputBarTest extends TestCase
{
    public function test_empty_series_returns_empty_list(): void
    {
        $this->assertSame([], ThroughputBar::fromSeries([]));
    }

    public function test_max_scaling_normalises_heights_to_largest_bucket(): void
    {
        $series = [
            new ThroughputBucket(at: new DateTimeImmutable('2026-05-06T10:00:00Z'), successCount: 10, failedCount: 0),
            new ThroughputBucket(at: new DateTimeImmutable('2026-05-06T10:05:00Z'), successCount: 18, failedCount: 2),
            new ThroughputBucket(at: new DateTimeImmutable('2026-05-06T10:10:00Z'), successCount: 5, failedCount: 0),
        ];

        $bars = ThroughputBar::fromSeries($series);

        $this->assertCount(3, $bars);
        // Max bucket total is 20 (success 18 + failed 2).
        $this->assertEqualsWithDelta(0.5, $bars[0]->successHeightRatio, 1e-9);
        $this->assertEqualsWithDelta(0.0, $bars[0]->failedHeightRatio, 1e-9);
        $this->assertEqualsWithDelta(0.9, $bars[1]->successHeightRatio, 1e-9);
        $this->assertEqualsWithDelta(0.1, $bars[1]->failedHeightRatio, 1e-9);
        $this->assertEqualsWithDelta(0.25, $bars[2]->successHeightRatio, 1e-9);
        $this->assertEqualsWithDelta(0.0, $bars[2]->failedHeightRatio, 1e-9);
    }

    public function test_all_zero_series_returns_zero_height_bars(): void
    {
        $series = [
            new ThroughputBucket(at: new DateTimeImmutable('2026-05-06T10:00:00Z'), successCount: 0, failedCount: 0),
            new ThroughputBucket(at: new DateTimeImmutable('2026-05-06T10:05:00Z'), successCount: 0, failedCount: 0),
        ];

        $bars = ThroughputBar::fromSeries($series);

        $this->assertCount(2, $bars);
        foreach ($bars as $bar) {
            $this->assertSame(0.0, $bar->successHeightRatio);
            $this->assertSame(0.0, $bar->failedHeightRatio);
        }
    }

    public function test_bars_preserve_input_timestamps_and_counts(): void
    {
        $at = new DateTimeImmutable('2026-05-06T10:00:00Z');
        $series = [
            new ThroughputBucket(at: $at, successCount: 7, failedCount: 3),
        ];

        $bars = ThroughputBar::fromSeries($series);

        $this->assertSame($at, $bars[0]->at);
        $this->assertSame(7, $bars[0]->successCount);
        $this->assertSame(3, $bars[0]->failedCount);
    }
}
