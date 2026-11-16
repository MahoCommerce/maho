<?php

/**
 * The bucket of a period dimension.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum Granularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case HourOfDay = 'hour_of_day';
    case DayOfWeek = 'day_of_week';

    /** A cyclic bucket (hour of day, day of week) groups many days into one row. */
    public function isCyclic(): bool
    {
        return $this === self::HourOfDay || $this === self::DayOfWeek;
    }

    /** The longest local date range, in days, that this granularity accepts. */
    public function maxDays(): ?int
    {
        return match ($this) {
            self::Hour => 31,
            self::Day => 400,
            default => null,
        };
    }
}
