<?php

/**
 * Forecast the total of a metric at the end of a calendar period, such as this month, at the pace of the period so far.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Report
 */

declare(strict_types=1);

namespace Maho\Report;

final class Forecast
{
    /** The part of the period that must pass before a forecast, because the first hours of a month are noise. */
    public const float MIN_ELAPSED = 0.1;

    /**
     * The value at the end of the period if the rest of the period has the same pace. Null before MIN_ELAPSED of the
     * period passed, and after the period.
     *
     * @param \DateTimeImmutable $start the first moment of the period
     * @param \DateTimeImmutable $end the first moment after the period
     */
    public static function atPace(float $value, \DateTimeImmutable $start, \DateTimeImmutable $end, \DateTimeImmutable $now): ?float
    {
        $length = $end->getTimestamp() - $start->getTimestamp();
        $elapsed = $now->getTimestamp() - $start->getTimestamp();
        if ($length <= 0 || $elapsed >= $length || $elapsed < $length * self::MIN_ELAPSED) {
            return null;
        }
        return $value * $length / $elapsed;
    }

    /**
     * The calendar period of a range that ends after today: the first moment of this month or this year, and the
     * first moment after it. Null for a range that ends with its data, such as the last 30 days.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}|null
     */
    public static function calendarPeriod(?DateRange $range, \DateTimeImmutable $now): ?array
    {
        $today = $now->setTime(0, 0);
        return match ($range) {
            DateRange::ThisMonth => [$today->modify('first day of this month'), $today->modify('first day of next month')],
            DateRange::ThisYear => [
                $today->setDate((int) $today->format('Y'), 1, 1),
                $today->setDate((int) $today->format('Y') + 1, 1, 1),
            ],
            default => null,
        };
    }
}
