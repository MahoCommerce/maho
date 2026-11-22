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
 * The customer cohorts: each customer email belongs to the month of its first placed order, and each later order to
 * the number of months after it. Customer A orders in January and March 2003, customer B first in March 2003. The first
 * order of customer A is in pounds.
 */

const EXPLORER_COHORT_EMAILS = ['cohort-a@example.test', 'cohort-b@example.test'];
// Its first order is late on a winter evening in Rome, and its next order in summer
const EXPLORER_COHORT_NIGHT_EMAIL = 'cohort-night@example.test';

function explorerCohortOrders(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_cohorts', 10.0);
    foreach ([
        ['2003-01-15 12:00:00', EXPLORER_COHORT_EMAILS[0], 'GBP'],
        ['2003-03-10 12:00:00', EXPLORER_COHORT_EMAILS[0], null],
        ['2003-03-20 12:00:00', EXPLORER_COHORT_EMAILS[1], null],
        ['2003-01-31 22:30:00', EXPLORER_COHORT_NIGHT_EMAIL, null],
        ['2003-06-15 12:00:00', EXPLORER_COHORT_NIGHT_EMAIL, null],
    ] as [$createdAt, $email, $currency]) {
        $order = ReportFixture::placeOrder($product, 1, $createdAt);
        ReportFixture::adapter()->update(
            ReportFixture::table('sales/order'),
            ['customer_email' => $email] + ($currency === null ? [] : ['order_currency_code' => $currency]),
            ['entity_id = ?' => (int) $order->getId()],
        );
    }
}

/**
 * The customers of each cohort cell, as "first order month/months after" => customers.
 *
 * @param array<string, mixed> $data
 * @return array<string, int>
 */
function explorerCohortCells(string $from, string $to, array $data = []): array
{
    $request = Request::fromArray($data + [
        'dataset' => 'sales_orders',
        'metrics' => ['unique_customers'],
        'dimensions' => ['first_order_month', 'months_since_first_order'],
        'from' => $from,
        'to' => $to,
        'scope' => 'all',
        'filters' => [['field' => 'customer_email', 'operator' => 'in', 'value' => EXPLORER_COHORT_EMAILS]],
    ]);
    $cells = [];
    foreach (Mage::getService('reports/explorer')->run($request, Mage_Reports_Model_Explorer_Access::full())['rows'] as $row) {
        $cells[$row['dimensions']['first_order_month'] . '/' . $row['dimensions']['months_since_first_order']] = (int) $row['values']['unique_customers'];
    }
    ksort($cells);
    return $cells;
}

/**
 * The rows of a request on the cohort orders of the first quarter of 2003.
 *
 * @param list<string> $dimensions
 * @return list<array<string, mixed>>
 */
function explorerCohortRows(array $dimensions, string $metric = 'unique_customers'): array
{
    return Mage::getService('reports/explorer')->run(Request::fromArray([
        'dataset' => 'sales_orders',
        'metrics' => [$metric],
        'dimensions' => $dimensions,
        'from' => '2003-01-01',
        'to' => '2003-03-31',
        'scope' => 'all',
        'filters' => [['field' => 'customer_email', 'operator' => 'in', 'value' => EXPLORER_COHORT_EMAILS]],
    ]), Mage_Reports_Model_Explorer_Access::full())['rows'];
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    explorerCohortOrders();
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

it('puts each customer in the month of the first order, and each order in the months after it', function () {
    expect(explorerCohortCells('2003-01-01', '2003-03-31'))->toBe([
        '2003-01/0' => 1,
        '2003-01/2' => 1,
        '2003-03/0' => 1,
    ]);
});

it('keeps the cohorts of an earlier first order out with the filter of the preset', function () {
    expect(explorerCohortCells('2003-03-01', '2003-03-31'))->toBe(['2003-01/2' => 1, '2003-03/0' => 1])
        ->and(explorerCohortCells('2003-03-01', '2003-03-31', [
            'filters' => [
                ['field' => 'customer_email', 'operator' => 'in', 'value' => EXPLORER_COHORT_EMAILS],
                ['field' => 'first_order_in_period', 'operator' => 'eq', 'value' => 1],
            ],
        ]))->toBe(['2003-03/0' => 1]);
});

it('offers the customer cohorts as a ready-made report with a matrix', function () {
    $preset = Mage::getSingleton('reports/explorer_registry')->getDataset('sales_orders')?->getPresets()['customer_cohorts'] ?? null;

    expect($preset?->chart)->toBe(Maho\Report\Definition\ChartType::Matrix)
        ->and($preset?->request['dimensions'])->toBe(['first_order_month', 'months_since_first_order']);
});

it('puts each customer in the week of the first order, with the first day of the week as its label', function () {
    $admin = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $firstDay = $admin->getConfig('general/locale/firstday');
    $admin->setConfig('general/locale/firstday', '1');
    try {
        $cells = [];
        foreach (explorerCohortRows(['first_order_week', 'weeks_since_first_order']) as $row) {
            $cells[$row['labels']['first_order_week'] . '/' . $row['dimensions']['weeks_since_first_order']] = (int) $row['values']['unique_customers'];
        }
        ksort($cells);

        // Monday 13 January, then 8 weeks later Monday 10 March, and the week of Monday 17 March
        expect($cells)->toBe(['2003-01-13/0' => 1, '2003-01-13/8' => 1, '2003-03-17/0' => 1]);
    } finally {
        $admin->setConfig('general/locale/firstday', (string) $firstDay);
    }
});

it('offers the weekly customer cohorts as a ready-made report with a matrix', function () {
    $preset = Mage::getSingleton('reports/explorer_registry')->getDataset('sales_orders')?->getPresets()['customer_cohorts_weekly'] ?? null;

    expect($preset?->chart)->toBe(Maho\Report\Definition\ChartType::Matrix)
        ->and($preset?->request['dimensions'])->toBe(['first_order_week', 'weeks_since_first_order']);
});

it('groups the orders by the currency of the order', function () {
    $orders = [];
    foreach (explorerCohortRows(['order_currency'], 'orders') as $row) {
        $orders[(string) $row['dimensions']['order_currency']] = (int) $row['values']['orders'];
    }

    expect($orders['GBP'] ?? null)->toBe(1)
        ->and(array_sum($orders))->toBe(3)
        ->and($orders)->toHaveCount(2);
});

it('leaves the cohort groupings out of the explanation of a change, and keeps new or returning', function () {
    $result = Mage::getService('reports/explorer')->explain(Request::fromArray([
        'dataset' => 'sales_orders',
        'metrics' => ['orders'],
        'from' => '2003-03-01',
        'to' => '2003-03-31',
        'compare' => 'previous_period',
        'scope' => 'all',
        'filters' => [['field' => 'customer_email', 'operator' => 'in', 'value' => EXPLORER_COHORT_EMAILS]],
    ]), Mage_Reports_Model_Explorer_Access::full(), 'orders');
    $codes = array_column($result['dimensions'], 'code');

    expect($codes)->toContain('customer_kind')
        ->and(array_intersect($codes, ['first_order_month', 'months_since_first_order', 'first_order_week', 'weeks_since_first_order']))->toBe([]);
});

it('puts a first order in its local month with the clock of its own season, also when the period has another one', function () {
    $admin = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $timezone = $admin->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
    $admin->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Europe/Rome');
    try {
        $rows = Mage::getService('reports/explorer')->run(Request::fromArray([
            'dataset' => 'sales_orders',
            'metrics' => ['orders'],
            'dimensions' => ['first_order_month', 'months_since_first_order'],
            'from' => '2003-06-01',
            'to' => '2003-06-30',
            'scope' => 'all',
            'filters' => [['field' => 'customer_email', 'operator' => 'eq', 'value' => EXPLORER_COHORT_NIGHT_EMAIL]],
        ]), Mage_Reports_Model_Explorer_Access::full())['rows'];

        // 22:30 UTC on 31 January is 23:30 in Rome in winter, and 00:30 on 1 February with the summer clock of June
        expect(array_column($rows, 'dimensions'))->toBe([['first_order_month' => '2003-01', 'months_since_first_order' => 5]]);
    } finally {
        $admin->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, (string) $timezone);
    }
});
