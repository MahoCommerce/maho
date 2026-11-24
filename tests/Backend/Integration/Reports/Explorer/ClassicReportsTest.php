<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Report\Request;
use Tests\Helpers\ReportFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The ready-made reports of the explorer give the same numbers as the classic aggregated reports, for orders of simple
 * products with one invoice and at most one credit memo. The metrics page of the docs lists where the two count
 * differently in other cases, such as several invoices of one order or the child items of a configurable product.
 *
 * Day one has an order with a coupon and an order that is refunded. Day two has an order with a coupon and an order
 * that is not invoiced.
 */

const EXPLORER_CLASSIC_DAY_ONE = '2004-09-14';
const EXPLORER_CLASSIC_DAY_TWO = '2004-09-15';
const EXPLORER_CLASSIC_COUPON = 'EXPLORERCLASSIC10';

/** @return array{product: Mage_Catalog_Model_Product} */
function explorerClassicOrders(): array
{
    static $fixtures = null;
    if ($fixtures !== null) {
        return $fixtures;
    }
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_classic', 10.0);
    ReportFixture::createCouponRule(EXPLORER_CLASSIC_COUPON);

    ReportFixture::placeOrder($product, 2, EXPLORER_CLASSIC_DAY_ONE . ' 10:00:00', couponCode: EXPLORER_CLASSIC_COUPON);
    $refunded = ReportFixture::placeOrder($product, 3, EXPLORER_CLASSIC_DAY_ONE . ' 11:00:00');
    ReportFixture::refundOrder($refunded);
    ReportFixture::moveOrderDates((int) $refunded->getId(), EXPLORER_CLASSIC_DAY_ONE . ' 11:00:00');
    ReportFixture::placeOrder($product, 1, EXPLORER_CLASSIC_DAY_TWO . ' 10:00:00', couponCode: EXPLORER_CLASSIC_COUPON);
    ReportFixture::placeOrder($product, 4, EXPLORER_CLASSIC_DAY_TWO . ' 11:00:00', invoice: false);

    ReportFixture::aggregate(['shipping', 'invoiced', 'refunded', 'coupons', 'bestsellers'], EXPLORER_CLASSIC_DAY_ONE, EXPLORER_CLASSIC_DAY_TWO);
    return $fixtures = ['product' => $product];
}

/**
 * The metrics of each day of an explorer report in store view 1.
 *
 * @param array<string, mixed> $request
 * @return array<string, array<string, float>>
 */
function explorerClassicDays(array $request): array
{
    $result = Mage::getService('reports/explorer')->run(
        Request::fromArray($request + ['dimensions' => ['period:day'], 'storeIds' => [1], 'from' => EXPLORER_CLASSIC_DAY_ONE, 'to' => EXPLORER_CLASSIC_DAY_TWO]),
        Mage_Reports_Model_Explorer_Access::full(),
    );
    $days = [];
    foreach ($result['rows'] as $row) {
        $days[(string) $row['dimensions']['period']] = array_map(floatval(...), $row['values']);
    }
    return $days;
}

/**
 * The sum of each column of a classic aggregated table for each day in store view 1.
 *
 * @param array<string, string> $columns metric code => column of the classic table
 * @param array<string, mixed> $where more conditions, column => value
 * @return array<string, array<string, float>>
 */
function explorerClassicTable(string $table, array $columns, array $where = []): array
{
    $adapter = ReportFixture::adapter();
    $days = [];
    foreach ([EXPLORER_CLASSIC_DAY_ONE, EXPLORER_CLASSIC_DAY_TWO] as $day) {
        $select = $adapter->select()
            ->from(ReportFixture::table($table), array_map(static fn(string $column): Maho\Db\Expr => new Maho\Db\Expr("SUM({$column})"), $columns))
            ->where('period = ?', $day)
            ->where('store_id = ?', 1);
        foreach ($where as $column => $value) {
            $select->where("{$column} = ?", $value);
        }
        $days[$day] = array_map(floatval(...), $adapter->fetchRow($select) ?: []);
    }
    return $days;
}

/**
 * @param array<string, array<string, float>> $explorer
 * @param array<string, array<string, float>> $classic
 */
function explorerExpectSameDays(array $explorer, array $classic): void
{
    foreach ($classic as $day => $values) {
        foreach ($values as $metric => $value) {
            expect($explorer[$day][$metric] ?? 0.0)->toEqualWithDelta($value, 0.0001, "{$metric} on {$day}");
        }
    }
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    explorerClassicOrders();
});

afterAll(function () {
    // tearDown() resets Mage after each test, so the cleanup starts it again and leaves it reset
    Mage::app();
    Mage::register('isSecureArea', true, true);
    try {
        ReportFixture::restore();
        ReportFixture::deleteFixtures();
    } finally {
        Mage::reset();
        restore_error_handler();
        restore_exception_handler();
    }
});

it('gives the numbers of the classic Shipping report by order date', function () {
    $columns = ['orders' => 'orders_count', 'shipping' => 'total_shipping', 'shipping_actual' => 'total_shipping_actual'];
    $explorer = explorerClassicDays([
        'dataset' => 'sales_orders',
        'metrics' => array_keys($columns),
        'filters' => [['field' => 'is_virtual', 'operator' => 'eq', 'value' => 0]],
    ]);

    expect(array_sum(array_column($explorer, 'orders')))->toBe(4.0);
    explorerExpectSameDays($explorer, explorerClassicTable('sales/shipping_aggregated_order', $columns));
});

it('gives the numbers of the classic Coupons report', function () {
    $columns = ['orders' => 'coupon_uses', 'gross_sales' => 'subtotal_amount', 'discount' => 'discount_amount', 'net_sales' => 'total_amount'];
    $explorer = explorerClassicDays([
        'dataset' => 'sales_orders',
        'scope' => 'all',
        'metrics' => array_keys($columns),
        'filters' => [['field' => 'has_coupon', 'operator' => 'eq', 'value' => 1]],
    ]);

    expect(array_sum(array_column($explorer, 'orders')))->toBe(2.0)
        ->and(array_sum(array_column($explorer, 'discount')))->toBeGreaterThan(0.0);
    explorerExpectSameDays($explorer, explorerClassicTable('salesrule/coupon_aggregated', $columns, ['coupon_code' => EXPLORER_CLASSIC_COUPON]));
});

it('gives the numbers of the classic Invoiced report by invoice date', function () {
    $columns = ['orders' => 'orders_count', 'invoiced' => 'invoiced'];
    $explorer = explorerClassicDays(['dataset' => 'sales_invoices', 'metrics' => array_keys($columns)]);

    expect(array_sum(array_column($explorer, 'orders')))->toBe(3.0);
    explorerExpectSameDays($explorer, explorerClassicTable('sales/invoiced_aggregated', $columns));
});

it('gives the numbers of the classic Refunds report by refund date', function () {
    $columns = ['orders' => 'orders_count', 'refunded' => 'refunded'];
    $explorer = explorerClassicDays(['dataset' => 'sales_creditmemos', 'metrics' => array_keys($columns)]);

    expect(array_sum(array_column($explorer, 'orders')))->toBe(1.0)
        ->and(array_sum(array_column($explorer, 'refunded')))->toBeGreaterThan(0.0);
    explorerExpectSameDays($explorer, explorerClassicTable('sales/refunded_aggregated', $columns));
});

it('gives the quantities of the classic Bestsellers report', function () {
    $productId = (int) explorerClassicOrders()['product']->getId();
    $explorer = explorerClassicDays([
        'dataset' => 'sales_items',
        'scope' => 'not_canceled',
        'metrics' => ['qty_ordered'],
        'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => $productId]],
    ]);

    expect(array_sum(array_column($explorer, 'qty_ordered')))->toBe(10.0);
    explorerExpectSameDays($explorer, explorerClassicTable('sales/bestsellers_aggregated_daily', ['qty_ordered' => 'qty_ordered'], ['product_id' => $productId]));
});
