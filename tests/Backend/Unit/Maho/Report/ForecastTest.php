<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Report\DateRange;
use Maho\Report\Forecast;

$at = static fn(string $time): DateTimeImmutable => new DateTimeImmutable($time, new DateTimeZone('Europe/Rome'));

it('forecasts the end of the month at the pace so far', function () use ($at) {
    [$start, $end] = Forecast::calendarPeriod(DateRange::ThisMonth, $at('2026-11-16 00:00'));

    // 15 of 30 days passed, so the month ends at twice the value
    expect(Forecast::atPace(1000.0, $start, $end, $at('2026-11-16 00:00')))->toBe(2000.0)
        ->and($start->format('Y-m-d H:i'))->toBe('2026-11-01 00:00')
        ->and($end->format('Y-m-d H:i'))->toBe('2026-12-01 00:00');
});

it('counts the hours of today in the pace', function () use ($at) {
    [$start, $end] = Forecast::calendarPeriod(DateRange::ThisMonth, $at('2026-11-10 12:00'));

    expect(Forecast::atPace(950.0, $start, $end, $at('2026-11-10 12:00')))->toEqualWithDelta(950.0 * 30 / 9.5, 0.0001);
});

it('forecasts the end of the year, with the length of a leap year', function () use ($at) {
    // In UTC, because the change to summer time takes one hour from the days so far
    $now = new DateTimeImmutable('2028-07-02 00:00', new DateTimeZone('UTC'));
    [$start, $end] = Forecast::calendarPeriod(DateRange::ThisYear, $now);

    // 2028 has 366 days, and 183 of them passed on 2 July
    expect(Forecast::atPace(183.0, $start, $end, $now))->toEqualWithDelta(366.0, 0.0001);
});

it('gives no forecast before a tenth of the period, or for a range that ends with its data', function () use ($at) {
    [$start, $end] = Forecast::calendarPeriod(DateRange::ThisMonth, $at('2026-11-02 09:00'));

    expect(Forecast::atPace(100.0, $start, $end, $at('2026-11-02 09:00')))->toBeNull()
        ->and(Forecast::calendarPeriod(DateRange::Last30Days, $at('2026-11-20 09:00')))->toBeNull()
        ->and(Forecast::calendarPeriod(null, $at('2026-11-20 09:00')))->toBeNull();
});
