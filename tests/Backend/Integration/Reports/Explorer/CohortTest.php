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
 * the number of months after it. Customer A orders in January and March 2003, customer B first in March 2003.
 */

const EXPLORER_COHORT_EMAILS = ['cohort-a@example.test', 'cohort-b@example.test'];

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
        ['2003-01-15 12:00:00', EXPLORER_COHORT_EMAILS[0]],
        ['2003-03-10 12:00:00', EXPLORER_COHORT_EMAILS[0]],
        ['2003-03-20 12:00:00', EXPLORER_COHORT_EMAILS[1]],
    ] as [$createdAt, $email]) {
        $order = ReportFixture::placeOrder($product, 1, $createdAt);
        ReportFixture::adapter()->update(ReportFixture::table('sales/order'), ['customer_email' => $email], ['entity_id = ?' => (int) $order->getId()]);
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
