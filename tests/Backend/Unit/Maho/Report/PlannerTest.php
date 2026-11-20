<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Report\Buckets;
use Maho\Report\Comparison;
use Maho\Report\DateRange;
use Maho\Report\Definition\Granularity;
use Maho\Report\Planner;
use Maho\Report\Request;

it('resolves a named range in the local time zone', function () {
    // 23:30 UTC on March 31 is already April 1 in Rome
    $now = new DateTimeImmutable('2026-03-31 23:30:00', new DateTimeZone('UTC'));
    [$from, $to] = Planner::localRange(Request::fromArray(['dataset' => 'x', 'range' => 'this_month']), $now, 'Europe/Rome');

    expect($from->format('Y-m-d'))->toBe('2026-04-01')->and($to->format('Y-m-d'))->toBe('2026-04-01');
});

it('compares with a previous period of the same length that ends the day before', function () {
    $zone = new DateTimeZone('UTC');
    [$from, $to] = Planner::compareRange(
        Comparison::PreviousPeriod,
        new DateTimeImmutable('2026-03-01', $zone),
        new DateTimeImmutable('2026-03-31', $zone),
    );
    expect($from->format('Y-m-d'))->toBe('2026-01-29')->and($to->format('Y-m-d'))->toBe('2026-02-28');
});

it('moves February 29 to February 28 for the previous year', function () {
    $zone = new DateTimeZone('UTC');
    [$from, $to] = Planner::compareRange(
        Comparison::PreviousYear,
        new DateTimeImmutable('2028-02-29', $zone),
        new DateTimeImmutable('2028-03-31', $zone),
    );
    expect($from->format('Y-m-d'))->toBe('2027-02-28')->and($to->format('Y-m-d'))->toBe('2027-03-31');
});

it('ends last month on its last day', function () {
    [$from, $to] = DateRange::LastMonth->resolve(new DateTimeImmutable('2026-03-31'));
    expect($from->format('Y-m-d'))->toBe('2026-02-01')->and($to->format('Y-m-d'))->toBe('2026-02-28');
});

it('lists every week key between two days for each first day of the week', function (int $firstDay, array $labels) {
    $zone = new DateTimeZone('UTC');
    $keys = Buckets::keys(Granularity::Week, new DateTimeImmutable('2026-11-01', $zone), new DateTimeImmutable('2026-11-16', $zone), $firstDay);
    $actual = array_map(static fn(int $key): string => Buckets::label(Granularity::Week, $key, $firstDay), $keys);
    expect($actual)->toBe($labels);
})->with([
    'Sunday' => [0, ['2026-11-01', '2026-11-08', '2026-11-15']],
    'Monday' => [1, ['2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16']],
]);

it('starts the weekday buckets on the first day of the week', function () {
    $zone = new DateTimeZone('UTC');
    $day = new DateTimeImmutable('2026-11-16', $zone);
    expect(Buckets::keys(Granularity::DayOfWeek, $day, $day, 1))->toBe([1, 2, 3, 4, 5, 6, 0]);
});

it('reads the raw SQL value of each engine as the same bucket key', function (Granularity $granularity, mixed $raw, string|int $key) {
    expect(Buckets::normalize($granularity, $raw))->toBe($key);
})->with([
    'MySQL hour' => [Granularity::Hour, '2026-11-16 13:00:00', '2026-11-16 13:00:00'],
    'PostgreSQL day' => [Granularity::Day, '2026-11-16', '2026-11-16'],
    'SQLite week' => [Granularity::Week, '20403.0', 20403],
    'PostgreSQL hour of day' => [Granularity::HourOfDay, '13.000000', 13],
]);
