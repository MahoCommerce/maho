<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Report\Request;
use Tests\Helpers\ReportFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The orders dataset of the report explorer reads the order tables live. These tests place real orders on days that no
 * other test uses, and compare the explorer with the hand-computed values and with the classic aggregated Orders report.
 */

const EXPLORER_DAY_ONE = '2004-03-10';
const EXPLORER_DAY_TWO = '2004-03-11';

function explorerRun(array $request, ?Mage_Reports_Model_Explorer_Access $access = null): array
{
    return Mage::getService('reports/explorer')->run(
        Request::fromArray($request + ['storeIds' => [1], 'from' => EXPLORER_DAY_ONE, 'to' => EXPLORER_DAY_TWO]),
        $access ?? Mage_Reports_Model_Explorer_Access::full(),
    );
}

/** @return array<string, float> the metrics of the row of $day */
function explorerDay(array $result, string $day): array
{
    foreach ($result['rows'] as $row) {
        if ($row['dimensions']['period'] === $day) {
            return $row['values'];
        }
    }
    throw new RuntimeException("No row for {$day}");
}

/**
 * Three orders that count, one of them refunded, and one canceled order. The first call saves the report tables and
 * places the orders. A test calls it, not beforeAll: the test case starts Mage in setUp() and stops it in tearDown().
 *
 * @return array{orders: list<Mage_Sales_Model_Order>, canceled: Mage_Sales_Model_Order}
 */
function explorerOrders(): array
{
    static $fixtures = null;
    if ($fixtures !== null) {
        return $fixtures;
    }
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer', 10.0);
    $orders = [
        ReportFixture::placeOrder($product, 2, EXPLORER_DAY_ONE . ' 12:00:00'),
        ReportFixture::placeOrder($product, 3, EXPLORER_DAY_ONE . ' 13:00:00'),
        ReportFixture::placeOrder($product, 1, EXPLORER_DAY_TWO . ' 12:00:00', invoice: false),
    ];
    $orders[0] = ReportFixture::refundOrder($orders[0]);

    $canceled = ReportFixture::placeOrder($product, 4, EXPLORER_DAY_TWO . ' 14:00:00', invoice: false);
    $canceled->cancel()->save();
    ReportFixture::moveOrderDates((int) $canceled->getId(), EXPLORER_DAY_TWO . ' 14:00:00');

    return $fixtures = ['orders' => $orders, 'canceled' => Mage::getModel('sales/order')->load($canceled->getId())];
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    explorerOrders();
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

describe('the orders dataset', function () {
    it('gives the hand-computed values of each day', function () {
        $result = explorerRun([
            'dataset' => 'sales_orders',
            'scope' => 'not_canceled',
            'metrics' => ['orders', 'units', 'gross_sales', 'returns', 'net_sales', 'sales_total', 'aov'],
            'dimensions' => ['period:day'],
        ]);
        [$refunded, $second, $third] = explorerOrders()['orders'];
        $grandTotal = static fn(Mage_Sales_Model_Order $o): float => (float) $o->getBaseGrandTotal() - (float) $o->getBaseTotalCanceled();

        $one = explorerDay($result, EXPLORER_DAY_ONE);
        expect($one['orders'])->toBe(2.0)
            ->and($one['units'])->toBe(5.0)
            ->and($one['gross_sales'])->toBe(50.0)
            ->and($one['returns'])->toBe(20.0)
            ->and($one['net_sales'])->toBe(30.0)
            ->and($one['sales_total'])->toEqualWithDelta($grandTotal($refunded) + $grandTotal($second), 0.0001)
            ->and($one['aov'])->toEqualWithDelta(($grandTotal($refunded) + $grandTotal($second)) / 2, 0.0001);

        $two = explorerDay($result, EXPLORER_DAY_TWO);
        expect($two['orders'])->toBe(1.0)->and($two['units'])->toBe(1.0)->and($two['sales_total'])->toEqualWithDelta($grandTotal($third), 0.0001);
        expect($result['totals']['values']['orders'])->toBe(3.0);
    });

    it('counts a canceled order as placed, with no amount and no unit', function () {
        $placed = explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders', 'units', 'sales_total']]);
        $notCanceled = explorerRun(['dataset' => 'sales_orders', 'scope' => 'not_canceled', 'metrics' => ['orders', 'units', 'sales_total']]);

        expect($placed['totals']['values']['orders'] - $notCanceled['totals']['values']['orders'])->toBe(1.0)
            ->and($placed['totals']['values']['units'])->toBe($notCanceled['totals']['values']['units'])
            ->and($placed['totals']['values']['sales_total'])->toEqualWithDelta($notCanceled['totals']['values']['sales_total'], 0.0001)
            ->and((float) explorerOrders()['canceled']->getBaseTotalCanceled())->toBeGreaterThan(0.0);
    });

    it('gives the same numbers as the classic Orders report for each day', function (string $dateField, string $table) {
        ReportFixture::aggregate(['sales'], EXPLORER_DAY_ONE, EXPLORER_DAY_TWO);
        $columns = [
            'orders' => 'orders_count', 'units' => 'total_qty_ordered', 'units_invoiced' => 'total_qty_invoiced',
            'sales_total' => 'total_income_amount', 'revenue' => 'total_revenue_amount', 'profit' => 'total_profit_amount',
            'invoiced' => 'total_invoiced_amount', 'paid' => 'total_paid_amount', 'refunded' => 'total_refunded_amount',
            'canceled' => 'total_canceled_amount', 'tax' => 'total_tax_amount', 'tax_actual' => 'total_tax_amount_actual',
            'shipping' => 'total_shipping_amount', 'shipping_actual' => 'total_shipping_amount_actual',
            'discount' => 'total_discount_amount', 'discount_actual' => 'total_discount_amount_actual',
        ];
        $result = explorerRun([
            'dataset' => 'sales_orders',
            'dateField' => $dateField,
            'metrics' => array_keys($columns),
            'dimensions' => ['period:day'],
        ]);

        $adapter = ReportFixture::adapter();
        foreach ([EXPLORER_DAY_ONE, EXPLORER_DAY_TWO] as $day) {
            $classic = $adapter->fetchRow($adapter->select()
                ->from(ReportFixture::table($table), array_map(static fn(string $c): Maho\Db\Expr => new Maho\Db\Expr("SUM({$c})"), $columns))
                ->where('period = ?', $day)
                ->where('store_id = ?', 1));
            $explorer = explorerDay($result, $day);
            foreach ($columns as $metric => $column) {
                expect($explorer[$metric])->toEqualWithDelta((float) $classic[$metric], 0.0001, "{$metric} on {$day}");
            }
        }
    })->with([
        'order created' => ['created_at', 'sales/order_aggregated_created'],
        'order updated' => ['updated_at', 'sales/order_aggregated_updated'],
    ]);

    it('compares with the earlier period at the same position', function () {
        $result = Mage::getService('reports/explorer')->run(Request::fromArray([
            'dataset' => 'sales_orders',
            'scope' => 'not_canceled',
            'metrics' => ['orders'],
            'dimensions' => ['period:day'],
            'from' => EXPLORER_DAY_TWO,
            'to' => EXPLORER_DAY_TWO,
            'compare' => 'previous_period',
            'storeIds' => [1],
        ]), Mage_Reports_Model_Explorer_Access::full());

        expect($result['comparePeriod'])->toBe(['from' => EXPLORER_DAY_ONE, 'to' => EXPLORER_DAY_ONE])
            ->and($result['rows'][0]['values']['orders'])->toBe(1.0)
            ->and($result['rows'][0]['compare']['orders'])->toBe(2.0)
            ->and($result['rows'][0]['change']['orders'])->toBe(['absolute' => -1.0, 'percent' => -50.0])
            ->and($result['totals']['compare']['orders'])->toBe(2.0);
    });

    it('buckets the orders by weekday and by week on every engine', function () {
        $weekday = explorerRun(['dataset' => 'sales_orders', 'scope' => 'not_canceled', 'metrics' => ['orders'], 'dimensions' => ['period:day_of_week']]);
        $byDay = [];
        foreach ($weekday['rows'] as $row) {
            $byDay[$row['dimensions']['period']] = $row['values']['orders'];
        }
        // 2004-03-10 was a Wednesday (3) and 2004-03-11 a Thursday (4)
        expect($byDay[3])->toBe(2.0)->and($byDay[4])->toBe(1.0)->and(array_sum($byDay))->toBe(3.0);

        $week = explorerRun(['dataset' => 'sales_orders', 'scope' => 'not_canceled', 'metrics' => ['orders'], 'dimensions' => ['period:week']]);
        expect($week['rows'])->toHaveCount(1)->and($week['rows'][0]['values']['orders'])->toBe(3.0);
    });
});

describe('the access of the caller', function () {
    it('hides the personal dimensions and refuses a request for them', function () {
        $access = new Mage_Reports_Model_Explorer_Access(null, false);
        $orders = array_first(array_filter(
            Mage::getService('reports/explorer')->datasets($access),
            static fn(array $dataset): bool => $dataset['code'] === 'sales_orders',
        ));

        expect(array_column($orders['dimensions'], 'code'))->not->toContain('customer_email')
            ->and(array_column($orders['presets'], 'code'))->not->toContain('customer_value_by_email')
            ->and(fn() => explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['customer_email']], $access))
            ->toThrow(Mage_Core_Exception_Input::class);

        try {
            explorerRun([
                'dataset' => 'sales_orders',
                'metrics' => ['orders'],
                'filters' => [['field' => 'customer_email', 'operator' => 'eq', 'value' => 'a@example.com']],
            ], $access);
            $this->fail('The request filtered on personal data without the permission.');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain('filters');
        }
    });

    it('gives a caller with a store limit its own stores when the request names none', function () {
        $access = new Mage_Reports_Model_Explorer_Access(null, true, [2]);
        $result = explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'storeIds' => null], $access);

        expect($result['request']['storeIds'])->toBe([2]);
    });

    it('refuses a store that the caller may not read', function () {
        $access = new Mage_Reports_Model_Explorer_Access(null, true, [2]);
        try {
            explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders']], $access);
            $this->fail('The request read a store that the caller may not read.');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain('storeIds');
        }
    });

    it('names every unknown code in one error', function () {
        try {
            explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders', 'nothing'], 'dimensions' => ['nowhere'], 'sort' => 'elsewhere']);
            $this->fail('The request with unknown codes passed.');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain('metrics', 'dimensions', 'sort');
        }
    });

    it('refuses an unknown date field with a field error, also with a period grouping', function () {
        try {
            explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:day'], 'dateField' => 'nowhere']);
            $this->fail('The request with an unknown date field passed.');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain('dateField');
        }
    });

    it('refuses a period by month of more than 100 years', function () {
        try {
            explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:month'], 'from' => '0001-01-01', 'to' => '9999-12-31']);
            $this->fail('The request of ten thousand years passed.');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toContain('dimensions');
        }
    });

    it('refuses a dataset that the caller may not read', function () {
        $access = new Mage_Reports_Model_Explorer_Access(['carts'], true);
        expect(fn() => explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders']], $access))
            ->toThrow(Mage_Core_Exception_Input::class);
    });
});

describe('the order items, the taxes, the downloads and the stock', function () {
    it('counts an order line once when its product is in two categories', function () {
        $pid = (int) explorerOrders()['orders'][1]->getAllItems()[0]->getProductId();
        $categories = [Mage_Catalog_Model_Category::TREE_ROOT_ID, (int) Mage::app()->getStore(1)->getRootCategoryId()];
        $table = ReportFixture::table('catalog/category_product');
        foreach ($categories as $id) {
            ReportFixture::adapter()->insert($table, ['category_id' => $id, 'product_id' => $pid, 'position' => 0]);
        }
        $run = static fn(array $filter, array $extra = []): array => explorerRun([
            'dataset' => 'sales_items', 'scope' => 'all', 'metrics' => ['lines'],
            'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => $pid], ['field' => 'category_id'] + $filter],
        ] + $extra);

        try {
            expect($run(['operator' => 'in', 'value' => $categories])['totals']['values']['lines'])->toBe(4.0)
                ->and($run(['operator' => 'neq', 'value' => 999999999])['totals']['values']['lines'])->toBe(4.0)
                ->and($run(['operator' => 'neq', 'value' => $categories[0]])['totals']['values']['lines'])->toBe(0.0);
            $grouped = $run(['operator' => 'in', 'value' => [$categories[0]]], ['dimensions' => ['category_id']]);
            expect(array_column(array_column($grouped['rows'], 'dimensions'), 'category_id'))->toEqual([$categories[0]])
                ->and($grouped['rows'][0]['values']['lines'])->toBe(4.0);
            $excluded = $run(['operator' => 'neq', 'value' => $categories[0]], ['dimensions' => ['category_id']]);
            expect($excluded['rows'])->toBe([]);
        } finally {
            ReportFixture::adapter()->delete($table, ['product_id = ?' => $pid]);
        }
    });

    it('counts the tax of a group of two rates once, in the explorer and in the classic report', function () {
        $order = explorerOrders()['orders'][1];
        $adapter = ReportFixture::adapter();
        $codes = ['explorer_a' => 2.0, 'explorer_b' => 1.0];
        foreach ($codes as $code => $real) {
            $adapter->insert(ReportFixture::table('sales/order_tax'), ['order_id' => $order->getId(), 'code' => $code, 'title' => $code,
                'percent' => 10, 'amount' => 3, 'base_amount' => 3, 'base_real_amount' => $real, 'priority' => 0, 'position' => 0, 'process' => 0]);
        }
        $expected = 3.0 * (float) $order->getBaseToGlobalRate();

        try {
            $result = explorerRun(['dataset' => 'sales_taxes', 'scope' => 'all', 'metrics' => ['tax'],
                'filters' => [['field' => 'code', 'operator' => 'in', 'value' => array_keys($codes)]]]);
            ReportFixture::aggregate(['tax'], EXPLORER_DAY_ONE, EXPLORER_DAY_ONE);
            $classic = (float) $adapter->fetchOne($adapter->select()
                ->from(ReportFixture::table('tax/tax_order_aggregated_created'), [new Maho\Db\Expr('SUM(tax_base_amount_sum)')])
                ->where('period = ?', EXPLORER_DAY_ONE)->where('store_id = ?', 1)->where('code IN (?)', array_keys($codes)));
            expect($result['totals']['values']['tax'])->toEqualWithDelta($expected, 0.0001)
                ->and($classic)->toEqualWithDelta($expected, 0.0001);
        } finally {
            $adapter->delete(ReportFixture::table('sales/order_tax'), ['order_id = ?' => $order->getId(), 'code IN (?)' => array_keys($codes)]);
        }
    });

    it('gives the gross sales of a partly canceled line on every database', function () {
        $product = ReportFixture::createProduct('explorer-partial', 10.0);
        $order = ReportFixture::placeOrder($product, 3, '2004-03-12 12:00:00');
        ReportFixture::adapter()->update(ReportFixture::table('sales/order_item'), ['qty_canceled' => 1], ['order_id = ?' => $order->getId()]);
        $result = explorerRun(['dataset' => 'sales_items', 'scope' => 'all', 'metrics' => ['gross_sales'], 'from' => '2004-03-12', 'to' => '2004-03-12',
            'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => (int) $product->getId()]]]);

        expect($result['totals']['values']['gross_sales'])->toEqualWithDelta(20.0 * (float) $order->getBaseToGlobalRate(), 0.0001);
    });

    it('finds an underscore and a percent sign as plain text', function () {
        $product = ReportFixture::createProduct('like_100%');
        ReportFixture::placeOrder($product, 1, '2004-03-13 12:00:00');
        $lines = static fn(string $text): float => (float) (explorerRun(['dataset' => 'sales_items', 'scope' => 'all', 'metrics' => ['lines'],
            'from' => '2004-03-13', 'to' => '2004-03-13',
            'filters' => [['field' => 'sku', 'operator' => 'contains', 'value' => $text]]])['totals']['values']['lines'] ?? 0);

        expect($lines('like_100%'))->toBe(1.0)
            ->and($lines('lik__100'))->toBe(0.0);
    });

    it('limits the downloads to the store views of the caller', function () {
        $order = explorerOrders()['orders'][1];
        $item = $order->getAllItems()[0];
        $adapter = ReportFixture::adapter();
        $adapter->insert(ReportFixture::table('downloadable/link_purchased'), ['order_id' => $order->getId(), 'order_item_id' => $item->getId(), 'customer_id' => null]);
        $purchasedId = (int) $adapter->lastInsertId(ReportFixture::table('downloadable/link_purchased'));
        $adapter->insert(ReportFixture::table('downloadable/link_purchased_item'), ['purchased_id' => $purchasedId,
            'order_item_id' => $item->getId(), 'product_id' => $item->getProductId(), 'number_of_downloads_bought' => 3]);
        $run = static fn(array $stores): float => (float) (Mage::getService('reports/explorer')->run(Request::fromArray([
            'dataset' => 'downloads', 'metrics' => ['purchases'],
            'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => (int) $item->getProductId()]],
        ]), new Mage_Reports_Model_Explorer_Access(null, true, $stores))['totals']['values']['purchases'] ?? 0);

        try {
            expect($run([2]))->toBe(0.0)->and($run([1]))->toBe(3.0);
        } finally {
            $adapter->delete(ReportFixture::table('downloadable/link_purchased'), ['purchased_id = ?' => $purchasedId]);
        }
    });

    it('counts the units sold in 30 days only in the store views of the caller', function () {
        $product = ReportFixture::createProduct('explorer-stock');
        ReportFixture::placeOrder($product, 2);
        $run = static fn(array $stores): float => (float) (Mage::getService('reports/explorer')->run(Request::fromArray([
            'dataset' => 'catalog_stock', 'metrics' => ['units_sold_30d'],
            'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => (int) $product->getId()]],
        ]), new Mage_Reports_Model_Explorer_Access(null, true, $stores))['totals']['values']['units_sold_30d'] ?? 0);

        expect($run([2]))->toBe(0.0)->and($run([1]))->toBe(2.0);
    });

    it('lists the newest day first when the request has no sort', function () {
        $days = static fn(array $extra): array => array_column(array_column(
            explorerRun(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:day']] + $extra)['rows'],
            'dimensions',
        ), 'period');

        expect($days([]))->toBe([EXPLORER_DAY_TWO, EXPLORER_DAY_ONE])
            ->and($days(['sort' => 'period']))->toBe([EXPLORER_DAY_ONE, EXPLORER_DAY_TWO]);
    });

    it('writes the earlier period in an export with a comparison', function () {
        $handle = fopen('php://memory', 'w+');
        Mage::getModel('reports/explorer_csv')->write($handle, Request::fromArray(['dataset' => 'sales_orders', 'scope' => 'not_canceled',
            'metrics' => ['orders'], 'dimensions' => ['period:day'], 'from' => EXPLORER_DAY_TWO, 'to' => EXPLORER_DAY_TWO,
            'compare' => 'previous_period', 'storeIds' => [1]]), Mage_Reports_Model_Explorer_Access::full(), 100);
        rewind($handle);
        $lines = array_map(static fn(string $line): array => str_getcsv($line, escape: ''), explode("\n", trim((string) stream_get_contents($handle))));
        fclose($handle);

        expect(array_slice($lines[1], 1, 2))->toBe(['1', '2']);
    });
});
