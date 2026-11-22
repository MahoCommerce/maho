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
 * An order report on a long period reads the daily totals of the orders, and gives the same numbers as the orders.
 * October has an invoiced order and a pending order, November a refunded order and a canceled order.
 */

const EXPLORER_DAILY_FROM = '2004-10-01';
const EXPLORER_DAILY_TO = '2004-11-30';

/** @return array{refunded: Mage_Sales_Model_Order, open: Mage_Sales_Model_Order} */
function explorerDailyOrders(): array
{
    static $orders = null;
    if ($orders !== null) {
        return $orders;
    }
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_daily', 10.0);
    ReportFixture::placeOrder($product, 2, '2004-10-05 12:00:00');
    $pending = ReportFixture::placeOrder($product, 3, '2004-10-05 13:00:00', invoice: false);
    ReportFixture::adapter()->update(ReportFixture::table('sales/order'), ['state' => 'new', 'status' => 'pending'], ['entity_id = ?' => (int) $pending->getId()]);
    $refunded = ReportFixture::placeOrder($product, 1, '2004-11-20 12:00:00');
    ReportFixture::refundOrder($refunded);
    ReportFixture::moveOrderDates((int) $refunded->getId(), '2004-11-20 12:00:00');
    $open = ReportFixture::placeOrder($product, 4, '2004-11-21 12:00:00', invoice: false);
    return $orders = ['refunded' => $refunded, 'open' => $open];
}

/** @param array<string, mixed> $data */
function explorerDailyRequest(array $data = []): Request
{
    return Request::fromArray($data + [
        'dataset' => 'sales_orders',
        'metrics' => ['orders', 'units', 'gross_sales', 'discount', 'returns', 'net_sales', 'sales_total', 'aov', 'refunded', 'canceled', 'new_customer_orders'],
        'dimensions' => ['period:day'],
        'storeIds' => [1],
        'from' => EXPLORER_DAILY_FROM,
        'to' => EXPLORER_DAILY_TO,
    ]);
}

/**
 * Run a request on the orders and on the daily totals, and return both results.
 *
 * @return array{live: array<string, mixed>, daily: array<string, mixed>}
 */
function explorerDailyBoth(Request $request): array
{
    $admin = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $service = Mage::getService('reports/explorer');
    $access = Mage_Reports_Model_Explorer_Access::full();
    $admin->setConfig(Mage_Reports_Model_Explorer_DailyTotals::XML_PATH_MIN_DAYS, '0');
    $live = $service->run($request, $access);
    $admin->setConfig(Mage_Reports_Model_Explorer_DailyTotals::XML_PATH_MIN_DAYS, '32');
    return ['live' => $live, 'daily' => $service->run($request, $access)];
}

/** @param array{live: array<string, mixed>, daily: array<string, mixed>} $both */
function explorerExpectSameResult(array $both): void
{
    expect($both['daily']['totals']['values'])->toEqualWithDelta($both['live']['totals']['values'], 0.0001)
        ->and(count($both['daily']['rows']))->toBe(count($both['live']['rows']));
    $daily = [];
    foreach ($both['daily']['rows'] as $row) {
        $daily[json_encode($row['dimensions'])] = $row['values'];
    }
    foreach ($both['live']['rows'] as $row) {
        expect($daily[json_encode($row['dimensions'])] ?? null)->toEqualWithDelta($row['values'], 0.0001);
    }
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    explorerDailyOrders();
    $this->daily = Mage::getSingleton('reports/explorer_dailyTotals');
    $this->daily->rebuild(EXPLORER_DAILY_FROM, EXPLORER_DAILY_TO);
    $this->daily->saveState(['timezone' => Mage_Reports_Model_Explorer_Runner::timezone(), 'from' => EXPLORER_DAILY_FROM, 'complete' => false, 'checked_at' => null]);
});

afterEach(function () {
    // Another test must not read a table that holds only these days
    $flag = Mage::getModel('core/flag', ['flag_code' => Mage_Reports_Model_Explorer_DailyTotals::FLAG_CODE])->loadSelf();
    if ($flag->getId()) {
        $flag->delete();
    }
    $adapter = ReportFixture::adapter();
    $adapter->delete(ReportFixture::table('reports/order_daily'));
    $adapter->delete(ReportFixture::table('reports/order_daily_change'));
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

it('reads the daily totals for a long period, with the numbers of the orders', function (array $data) {
    $request = explorerDailyRequest($data);
    $runner = new ReflectionMethod(Mage_Reports_Model_Explorer_Runner::class, 'prepare');
    [$plan] = $runner->invoke(Mage::getSingleton('reports/explorer_runner'), $request, Mage_Reports_Model_Explorer_Access::full(), 1000, true);

    expect($plan->dataset->table)->toBe(ReportFixture::table('reports/order_daily'));
    $both = explorerDailyBoth($request);
    expect($both['live']['totals']['values']['orders'])->toBeGreaterThanOrEqual(3.0);
    explorerExpectSameResult($both);
})->with([
    'by day' => [[]],
    'by month, all orders' => [['dimensions' => ['period:month'], 'scope' => 'all']],
    'by status, not canceled' => [['dimensions' => ['status', 'customer_kind'], 'scope' => 'not_canceled']],
    'by weekday, with a filter' => [['dimensions' => ['period:day_of_week'], 'filters' => [['field' => 'customer_type', 'operator' => 'eq', 'value' => 'guest']]]],
]);

it('calculates a day again after an order of that day changes', function () {
    $open = Mage::getModel('sales/order')->load(explorerDailyOrders()['open']->getId());
    $open->cancel()->save();

    $both = explorerDailyBoth(explorerDailyRequest(['dimensions' => ['status'], 'scope' => 'all']));
    expect($both['live']['totals']['values']['canceled'])->toBeGreaterThan(0.0);
    explorerExpectSameResult($both);
});

it('reads the orders when the request does not fit the daily totals', function (array $data) {
    $runner = new ReflectionMethod(Mage_Reports_Model_Explorer_Runner::class, 'prepare');
    [$plan] = $runner->invoke(Mage::getSingleton('reports/explorer_runner'), explorerDailyRequest($data), Mage_Reports_Model_Explorer_Access::full(), 1000, true);

    expect($plan->dataset->table)->toBe(ReportFixture::table('sales/order'));
})->with([
    'a short period' => [['from' => '2004-10-01', 'to' => '2004-10-05']],
    'a grouping that the table does not keep' => [['dimensions' => ['payment_method']]],
    'a metric that does not add up' => [['metrics' => ['unique_customers']]],
    'the date of the last change' => [['dateField' => 'updated_at']],
    'a period before the built history' => [['from' => '2004-09-01']],
]);

it('builds a range with more rows than one insert of the database takes', function () {
    $daily = new class extends Mage_Reports_Model_Explorer_DailyTotals {
        public const int MAX_INSERT_VALUES = 1;
    };
    $daily->rebuild(EXPLORER_DAILY_FROM, EXPLORER_DAILY_TO);

    $rows = (int) ReportFixture::adapter()->fetchOne(ReportFixture::adapter()->select()
        ->from(ReportFixture::table('reports/order_daily'), [new Maho\Db\Expr('COUNT(*)')])
        ->where('day >= ?', EXPLORER_DAILY_FROM)->where('day <= ?', EXPLORER_DAILY_TO));
    expect($rows)->toBeGreaterThan(1);
    explorerExpectSameResult(explorerDailyBoth(explorerDailyRequest()));
});
