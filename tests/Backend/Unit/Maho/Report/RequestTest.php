<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Report\Comparison;
use Maho\Report\DateRange;
use Maho\Report\Definition\Granularity;
use Maho\Report\Operator;
use Maho\Report\Request;

it('reads the compact forms of the API query string', function () {
    $request = Request::fromArray([
        'dataset' => 'sales_orders',
        'metrics' => 'orders, net_sales',
        'dimensions' => 'period:month,payment_method',
        'filters' => '[{"field":"payment_method","operator":"in","value":"checkmo"}]',
        'sort' => '-net_sales',
        'range' => 'last_12_months',
        'compare' => 'previous_year',
        'storeIds' => '1,2',
        'limit' => '50',
    ]);

    expect($request->errors)->toBe([])
        ->and($request->metrics)->toBe(['orders', 'net_sales'])
        ->and($request->dimensions[0]->granularity)->toBe(Granularity::Month)
        ->and($request->dimensions[1]->code)->toBe('payment_method')
        ->and($request->filters[0]->operator)->toBe(Operator::In)
        ->and($request->filters[0]->value)->toBe(['checkmo'])
        ->and($request->sortKey)->toBe('net_sales')
        ->and($request->sortDescending)->toBeTrue()
        ->and($request->range)->toBe(DateRange::Last12Months)
        ->and($request->compare)->toBe(Comparison::PreviousYear)
        ->and($request->storeIds)->toBe([1, 2])
        ->and($request->limit)->toBe(50);
});

it('gives the same request back after a round trip through its array', function () {
    $request = Request::fromArray([
        'dataset' => 'sales_items',
        'metrics' => ['units'],
        'dimensions' => [['code' => 'sku']],
        'filters' => [['field' => 'sku', 'operator' => 'contains', 'value' => 'abc']],
        'sort' => ['key' => 'units', 'direction' => 'asc'],
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ]);

    expect(Request::fromArray($request->toArray())->toArray())->toBe($request->toArray())
        ->and($request->toArray())->not->toHaveKey('limit');
});

it('collects every unreadable value as a field error instead of throwing', function () {
    $request = Request::fromArray([
        'metrics' => [1],
        'dimensions' => ['period:fortnight'],
        'filters' => '{not json',
        'from' => '2026-02-30',
        'compare' => 'last_decade',
        'limit' => '0',
    ]);

    $fields = array_column($request->errors, 'field');
    expect($fields)->toContain('dataset', 'metrics', 'dimensions', 'filters', 'from', 'compare', 'limit');
});

it('drops the value of a filter whose operator takes none', function () {
    $request = Request::fromArray([
        'dataset' => 'carts',
        'filters' => [['field' => 'customer_email', 'operator' => 'not_empty', 'value' => 'x']],
    ]);
    expect($request->filters[0]->value)->toBeNull();
});
