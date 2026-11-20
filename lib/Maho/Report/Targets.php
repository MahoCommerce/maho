<?php

/**
 * Spread monthly targets of a metric over the buckets of a period.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Granularity;

final class Targets
{
    /**
     * Each day gets the target of its month divided by the days of the month. A bucket gets the sum of its days in
     * the period, and the total is the sum of every day of the period. A month without a target counts 0, and a
     * period without any target gives null.
     *
     * @param array<string, float> $monthly the targets by month, as Y-m
     * @return array{buckets: array<string|int, float>, total: float}|null
     */
    public static function spread(array $monthly, Granularity $granularity, \DateTimeImmutable $from, \DateTimeImmutable $to, int $firstDayOfWeek = 0): ?array
    {
        if (Buckets::keyOf($granularity, $from, $firstDayOfWeek) === null) {
            return null;
        }
        $buckets = [];
        $total = 0.0;
        $found = false;
        for ($day = $from->setTime(0, 0), $last = $to->setTime(0, 0); $day <= $last; $day = $day->modify('+1 day')) {
            $month = $day->format('Y-m');
            $found = $found || isset($monthly[$month]);
            $share = ($monthly[$month] ?? 0.0) / (int) $day->format('t');
            $key = (string) Buckets::keyOf($granularity, $day, $firstDayOfWeek);
            $buckets[$key] = ($buckets[$key] ?? 0.0) + $share;
            $total += $share;
        }
        if (!$found) {
            return null;
        }
        return ['buckets' => array_map(static fn(float $value): float => round($value, 4), $buckets), 'total' => round($total, 4)];
    }
}
