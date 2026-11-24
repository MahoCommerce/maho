<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Granularity;
use Maho\Report\Definition\Metric;
use Maho\Report\Explainer;
use Maho\Report\Formula;
use Maho\Report\Targets;

it('ranks the values by the size of their change and gives each one its share of the total change', function () {
    $drivers = Explainer::drivers([
        'pending' => ['label' => 'Pending', 'current' => 0.0, 'earlier' => 3.0],
        'processing' => ['label' => 'Processing', 'current' => 1.0, 'earlier' => 2.0],
        'complete' => ['label' => 'Complete', 'current' => 4.0, 'earlier' => 4.0],
        'new' => ['label' => 'New', 'current' => 2.0, 'earlier' => null],
    ], -2.0, true);

    expect(array_column($drivers, 'value'))->toBe(['pending', 'new', 'processing'])
        ->and($drivers[0])->toMatchArray(['change' => -3.0, 'percent' => -100.0, 'share' => 150.0])
        ->and($drivers[1])->toMatchArray(['change' => 2.0, 'percent' => null, 'share' => -100.0])
        ->and($drivers[2]['share'])->toBe(50.0);
});

it('gives no share when the values do not add up to the total, and keeps the top values only', function () {
    $values = [];
    foreach (range(1, 8) as $i) {
        $values["v{$i}"] = ['label' => "V{$i}", 'current' => (float) $i, 'earlier' => 0.0];
    }
    $drivers = Explainer::drivers($values, 36.0, false, 3);

    expect(array_column($drivers, 'value'))->toBe(['v8', 'v7', 'v6'])
        ->and(array_column($drivers, 'share'))->toBe([null, null, null]);
});

it('gives no share when the changes cancel out to a total change of zero', function () {
    $drivers = Explainer::drivers([
        'a' => ['label' => 'A', 'current' => 3.0, 'earlier' => 1.0],
        'b' => ['label' => 'B', 'current' => 1.0, 'earlier' => 3.0],
    ], 0.0, true);

    expect(array_column($drivers, 'value'))->toBe(['a', 'b'])
        ->and(array_column($drivers, 'share'))->toBe([null, null]);
});

it('gives no percent to a value whose earlier amount is under 5% of the earlier amounts of the dimension', function () {
    $drivers = Explainer::drivers([
        'complete' => ['label' => 'Complete', 'current' => 15.0, 'earlier' => 32.0],
        'processing' => ['label' => 'Processing', 'current' => 9.0, 'earlier' => 1.0],
        'canceled' => ['label' => 'Canceled', 'current' => 1.0, 'earlier' => 2.0],
        'closed' => ['label' => 'Closed', 'current' => 2.0, 'earlier' => 1.0],
    ], -9.0, true);

    expect(array_column($drivers, 'value'))->toBe(['complete', 'processing', 'canceled', 'closed'])
        ->and(array_column($drivers, 'percent'))->toBe([-53.13, null, -50.0, null])
        ->and(array_column($drivers, 'change'))->toBe([-17.0, 8.0, -1.0, 1.0]);
});

it('knows which formulas only add and subtract', function () {
    expect(Formula::parse('gross_sales - discount - returns')->isLinear())->toBeTrue()
        ->and(Formula::parse('-a + b')->isLinear())->toBeTrue()
        ->and(Formula::parse('sales / orders')->isLinear())->toBeFalse()
        ->and(Formula::parse('a * 2')->isLinear())->toBeFalse()
        ->and(Formula::parse('a + 5')->isLinear())->toBeFalse();
});

it('knows which metrics add up over the groups', function () {
    $dataset = new Dataset('test', 'Test', 'test', 'report', 'table');
    $dataset->addField(new Field('amount', 'Amount', FieldType::Money, 'main.amount'));
    $dataset->addField(new Field('email', 'Email', FieldType::String, 'main.email'));
    $dataset->addMetric(Metric::aggregate('orders', 'Orders', Aggregate::Count, null));
    $dataset->addMetric(Metric::aggregate('sales', 'Sales', Aggregate::Sum, 'amount'));
    $dataset->addMetric(Metric::aggregate('customers', 'Customers', Aggregate::CountDistinct, 'email'));
    $dataset->addMetric(Metric::aggregate('largest', 'Largest', Aggregate::Max, 'amount'));
    $dataset->addMetric(Metric::formula('net', 'Net', 'sales - orders'));
    $dataset->addMetric(Metric::formula('average', 'Average', 'sales / orders'));
    $dataset->addMetric(Metric::formula('mixed', 'Mixed', 'sales - customers'));

    $additive = array_filter(['orders', 'sales', 'customers', 'largest', 'net', 'average', 'mixed'], $dataset->isAdditive(...));
    expect(array_values($additive))->toBe(['orders', 'sales', 'net'])
        ->and(array_keys($dataset->additiveParts('average') ?? []))->toBe(['sales', 'orders'])
        ->and(array_keys($dataset->additiveParts('net') ?? []))->toBe(['sales', 'orders'])
        ->and($dataset->additiveParts('mixed'))->toBeNull()
        ->and($dataset->additiveParts('largest'))->toBeNull();
});

it('spreads a monthly target over the days and adds the days of each bucket', function () {
    $zone = new DateTimeZone('UTC');
    $monthly = ['2026-11' => 3000.0, '2026-12' => 3100.0];

    $days = Targets::spread($monthly, Granularity::Day, new DateTimeImmutable('2026-11-29', $zone), new DateTimeImmutable('2026-12-01', $zone));
    $months = Targets::spread($monthly, Granularity::Month, new DateTimeImmutable('2026-11-16', $zone), new DateTimeImmutable('2026-12-31', $zone));
    $weeks = Targets::spread($monthly, Granularity::Week, new DateTimeImmutable('2026-11-29', $zone), new DateTimeImmutable('2026-12-05', $zone));

    expect($days)->toBe(['buckets' => ['2026-11-29' => 100.0, '2026-11-30' => 100.0, '2026-12-01' => 100.0], 'total' => 300.0])
        ->and($months)->toBe(['buckets' => ['2026-11' => 1500.0, '2026-12' => 3100.0], 'total' => 4600.0])
        ->and($weeks['total'])->toBe(700.0)
        ->and(count($weeks['buckets']))->toBe(1)
        ->and(Targets::spread([], Granularity::Day, new DateTimeImmutable('2026-11-01', $zone), new DateTimeImmutable('2026-11-02', $zone)))->toBeNull()
        ->and(Targets::spread($monthly, Granularity::HourOfDay, new DateTimeImmutable('2026-11-01', $zone), new DateTimeImmutable('2026-11-02', $zone)))->toBeNull();
});

it('gives every day of a week the key of the first day of that week', function () {
    $key = static fn(string $day, int $firstDay) => Maho\Report\Buckets::keyOf(Granularity::Week, new DateTimeImmutable($day, new DateTimeZone('UTC')), $firstDay);
    $label = static fn(string $day, int $firstDay) => Maho\Report\Buckets::label(Granularity::Week, $key($day, $firstDay), $firstDay);

    expect(array_unique(array_map(static fn(string $d) => $key($d, 0), ['2026-11-15', '2026-11-18', '2026-11-21'])))->toHaveCount(1)
        ->and($label('2026-11-18', 0))->toBe('2026-11-15')
        ->and($label('2026-11-18', 1))->toBe('2026-11-16')
        ->and($label('2026-11-15', 1))->toBe('2026-11-09')
        ->and($label('1969-12-31', 0))->toBe('1969-12-28')
        ->and(Maho\Report\Buckets::keyOf(Granularity::Month, new DateTimeImmutable('2026-02-28')))->toBe('2026-02')
        ->and(Maho\Report\Buckets::keyOf(Granularity::HourOfDay, new DateTimeImmutable('2026-02-28')))->toBeNull();
});
