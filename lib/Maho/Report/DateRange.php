<?php

/**
 * A named local date range, such as the last 30 days.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

enum DateRange: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Last12Months = 'last_12_months';

    /**
     * The first and the last day of the range, at midnight in the time zone of $today.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function resolve(\DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        return match ($this) {
            self::Today => [$today, $today],
            self::Yesterday => [$today->modify('-1 day'), $today->modify('-1 day')],
            self::Last7Days => [$today->modify('-6 days'), $today],
            self::Last30Days => [$today->modify('-29 days'), $today],
            self::Last90Days => [$today->modify('-89 days'), $today],
            self::ThisMonth => [$today->modify('first day of this month'), $today],
            self::LastMonth => [$today->modify('first day of last month'), $today->modify('last day of last month')],
            self::ThisYear => [$today->setDate((int) $today->format('Y'), 1, 1), $today],
            self::LastYear => [
                $today->setDate((int) $today->format('Y') - 1, 1, 1),
                $today->setDate((int) $today->format('Y') - 1, 12, 31),
            ],
            self::Last12Months => [$today->modify('first day of this month')->modify('-11 months'), $today],
        };
    }
}
