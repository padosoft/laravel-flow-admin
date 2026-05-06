<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Unit\Support;

use Padosoft\LaravelFlowAdmin\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusLabelProvider(): array
    {
        return [
            'running' => ['running', 'Running'],
            'success' => ['success', 'Succeeded'],
            'failed' => ['failed', 'Failed'],
            'paused' => ['paused', 'Paused'],
            'pending' => ['pending', 'Pending'],
            'compensated' => ['compensated', 'Compensated'],
            'delivered' => ['delivered', 'Delivered'],
            'dead' => ['dead', 'Dead-letter'],
            'granted' => ['granted', 'Granted'],
            'rejected' => ['rejected', 'Rejected'],
            'expired' => ['expired', 'Expired'],
            'unknown slug' => ['some-future-vendor-status', 'some-future-vendor-status'],
        ];
    }

    #[DataProvider('statusLabelProvider')]
    public function test_status_label(string $slug, string $expected): void
    {
        $this->assertSame($expected, Format::statusLabel($slug));
    }

    /**
     * @return array<string, array{0: ?int, 1: string}>
     */
    public static function durationLabelProvider(): array
    {
        return [
            'null is em-dash' => [null, '—'],
            'zero is em-dash' => [0, '—'],
            '500ms' => [500, '500ms'],
            '999ms last sub-second' => [999, '999ms'],
            '1.00s' => [1000, '1.00s'],
            '12.40s' => [12_400, '12.40s'],
            '59.99s last sub-min' => [59_999, '60.00s'],
            '2.5m' => [150_000, '2.5m'],
            '59.9m' => [3_599_999, '60.0m'],
            '1.5h' => [5_400_000, '1.5h'],
            '24.0h' => [86_400_000, '24.0h'],
        ];
    }

    #[DataProvider('durationLabelProvider')]
    public function test_duration_label(?int $ms, string $expected): void
    {
        $this->assertSame($expected, Format::durationLabel($ms));
    }

    public function test_percent_label_rounds_half_up(): void
    {
        $this->assertSame('0.0%', Format::percentLabel(0.0));
        $this->assertSame('50.0%', Format::percentLabel(0.5));
        $this->assertSame('95.2%', Format::percentLabel(0.952));
        $this->assertSame('100.0%', Format::percentLabel(1.0));
    }

    public function test_delta_label_uses_typographic_minus(): void
    {
        $this->assertSame('+12', Format::deltaLabel(12));
        $this->assertSame('0', Format::deltaLabel(0));
        // U+2212 (MINUS SIGN), not the ASCII hyphen-minus.
        $this->assertSame("\u{2212}5", Format::deltaLabel(-5));
    }
}
