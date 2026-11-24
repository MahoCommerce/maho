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
 * The customer segments are a grouping and a filter of the orders and of the customers. A registered customer in the
 * segment orders once, and a guest orders once, on a day that no other test uses.
 */

const EXPLORER_SEGMENT_DAY = '2004-12-07';

/** @return array{segment: int, customer: Mage_Customer_Model_Customer} */
function explorerSegmentFixtures(): array
{
    static $fixtures = null;
    if ($fixtures !== null) {
        return $fixtures;
    }
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_segment', 10.0);
    $customer = ReportFixture::createCustomer('explorer_segment', EXPLORER_SEGMENT_DAY . ' 09:00:00');
    ReportFixture::placeOrder($product, 2, EXPLORER_SEGMENT_DAY . ' 10:00:00', customer: $customer);
    ReportFixture::placeOrder($product, 1, EXPLORER_SEGMENT_DAY . ' 11:00:00');

    $segment = Mage::getModel('customersegmentation/segment')
        ->setData(['name' => 'Explorer test segment', 'is_active' => 1, 'website_ids' => '1', 'customer_group_ids' => '0,1,2,3', 'refresh_mode' => 'manual'])
        ->save();
    ReportFixture::adapter()->insert(ReportFixture::table('customersegmentation/segment_customer'), [
        'segment_id' => (int) $segment->getId(),
        'customer_id' => (int) $customer->getId(),
        'website_id' => 1,
    ]);
    return $fixtures = ['segment' => (int) $segment->getId(), 'customer' => $customer];
}

/**
 * The totals and the rows by segment of one dataset on the test day.
 *
 * @param list<array<string, mixed>> $filters
 * @return array<string, mixed>
 */
function explorerSegmentRun(string $dataset, string $metric, array $dimensions = [], array $filters = []): array
{
    return Mage::getService('reports/explorer')->run(Request::fromArray([
        'dataset' => $dataset,
        'metrics' => [$metric],
        'dimensions' => $dimensions,
        'filters' => $filters,
        'scope' => $dataset === 'sales_orders' ? 'all' : null,
        'from' => EXPLORER_SEGMENT_DAY,
        'to' => EXPLORER_SEGMENT_DAY,
    ]), Mage_Reports_Model_Explorer_Access::full());
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    $this->segment = explorerSegmentFixtures()['segment'];
});

afterAll(function () {
    // tearDown() resets Mage after each test, so the cleanup starts it again and leaves it reset
    Mage::app();
    Mage::register('isSecureArea', true, true);
    try {
        Mage::getModel('customersegmentation/segment')->load(explorerSegmentFixtures()['segment'])->delete();
        ReportFixture::restore();
        ReportFixture::deleteFixtures();
    } finally {
        Mage::reset();
        restore_error_handler();
        restore_exception_handler();
    }
});

it('groups the orders by the segment of their customer, with the guests under none', function () {
    $result = explorerSegmentRun('sales_orders', 'orders', ['customer_segment']);
    $orders = [];
    foreach ($result['rows'] as $row) {
        $orders[(string) $row['dimensions']['customer_segment']] = $row['values']['orders'];
    }

    expect($orders)->toEqual([(string) $this->segment => 1.0, '' => 1.0])
        ->and($result['totals']['values']['orders'])->toBe(2.0)
        ->and($result['warnings'])->not->toBe([]);
});

it('keeps or drops the orders of a segment with a filter', function () {
    $segment = $this->segment;
    $filter = static fn(string $operator): array => [['field' => 'customer_segment', 'operator' => $operator, 'value' => $segment]];

    expect(explorerSegmentRun('sales_orders', 'orders', [], $filter('eq'))['totals']['values']['orders'])->toBe(1.0)
        ->and(explorerSegmentRun('sales_orders', 'orders', [], $filter('neq'))['totals']['values']['orders'])->toBe(1.0);
});

it('counts the customers of a segment', function () {
    $result = explorerSegmentRun('customers', 'registrations', [], [['field' => 'customer_segment', 'operator' => 'eq', 'value' => $this->segment]]);

    expect($result['totals']['values']['registrations'])->toBe(1.0);
});
