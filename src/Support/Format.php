<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

/**
 * Pre-formatting helpers for the View-Model layer. Mirrors the helpers
 * in `.design-source/project/ui.jsx` so the Blade output matches the
 * design prototype byte-for-byte where possible. Pure / static / no
 * dependencies — easy to unit-test without booting a full app.
 */
final class Format
{
    /**
     * Mirror of `ui.jsx::StatusBadge.labels`. An unknown slug falls
     * through verbatim so a future vendor status renders gracefully
     * instead of throwing.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'running' => 'Running',
            'success' => 'Succeeded',
            'failed' => 'Failed',
            'paused' => 'Paused',
            'pending' => 'Pending',
            'compensated' => 'Compensated',
            'delivered' => 'Delivered',
            'dead' => 'Dead-letter',
            'granted' => 'Granted',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            default => $status,
        };
    }

    /**
     * Mirror of `ui.jsx::fmtDuration`.
     * - null / 0  → `—`
     * - <1s       → `<n>ms`
     * - <1m       → `<n>.<dd>s`
     * - <1h       → `<n>.<d>m`
     * - else      → `<n>.<d>h`
     */
    public static function durationLabel(?int $ms): string
    {
        if ($ms === null || $ms === 0) {
            return '—';
        }

        if ($ms < 1000) {
            return $ms . 'ms';
        }

        if ($ms < 60_000) {
            return number_format($ms / 1000, 2) . 's';
        }

        if ($ms < 3_600_000) {
            return number_format($ms / 60_000, 1) . 'm';
        }

        return number_format($ms / 3_600_000, 1) . 'h';
    }

    /**
     * Render a 0..1 ratio as a percentage string with no decimals
     * (`0.952` → `"95%"`). Matches the KPI tile display rule from
     * `.design-source/project/page-overview.jsx`.
     */
    public static function percentLabel(float $ratio): string
    {
        return ((int) round($ratio * 100)) . '%';
    }

    /**
     * Render a signed integer delta as `+12` / `−5` / `0`. The minus
     * sign is the typographic U+2212 to match the design source's
     * KPI delta arrow + value layout.
     */
    public static function deltaLabel(int $delta): string
    {
        if ($delta > 0) {
            return '+' . $delta;
        }

        if ($delta < 0) {
            return "\u{2212}" . abs($delta);
        }

        return '0';
    }
}
