<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 orders report on a day far in the past, so the report of that day holds only the fixture order.
 *
 * @group read
 */

const REPORT_ORDERS_PATH = '/api/rest/v2/reports/sales/orders';
const REPORT_ORDERS_DAY = '2003-01-15';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('report-orders');
    $GLOBALS['reportOrdersOrder'] = ReportFixture::placeOrder($product, 2, REPORT_ORDERS_DAY . ' 12:00:00');
    ReportFixture::aggregate(['sales'], REPORT_ORDERS_DAY, REPORT_ORDERS_DAY);
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportOrdersGet(string $query): array
{
    return apiGet(REPORT_ORDERS_PATH . '?' . $query, adminToken());
}

describe('Orders report', function (): void {

    it('returns the exact numbers of the day and fills the empty days next to it', function (): void {
        /** @var Mage_Sales_Model_Order $order */
        $order = $GLOBALS['reportOrdersOrder'];
        $rate = (float) $order->getBaseToGlobalRate();

        $response = reportOrdersGet('from=2003-01-14&to=2003-01-16');
        expect($response['status'])->toBe(200);
        $json = $response['json'];

        expect($json['report'])->toBe('sales-orders')
            ->and($json['currency'])->toBe(Mage::app()->getBaseCurrencyCode())
            ->and($json['periodType'])->toBe('day')
            ->and($json['dateBasis'])->toBe('created')
            ->and($json['statistics']['code'])->toBe('sales')
            ->and(array_column($json['periods'], 'period'))->toBe(['2003-01-14', '2003-01-15', '2003-01-16']);

        $day = $json['periods'][1]['values'];
        expect($day['ordersCount'])->toBe(1)
            ->and((float) $day['qtyOrdered'])->toEqual(2.0)
            ->and((float) $day['salesTotal'])->toEqualWithDelta((float) $order->getBaseGrandTotal() * $rate, 0.0001)
            ->and((float) $day['invoiced'])->toEqualWithDelta((float) $order->getBaseTotalInvoiced() * $rate, 0.0001)
            ->and((float) $day['invoiced'])->toBeGreaterThan(0.0);

        expect($json['periods'][0]['values']['ordersCount'])->toBe(0)
            ->and((float) $json['periods'][0]['values']['salesTotal'])->toEqual(0.0)
            ->and($json['totals']['ordersCount'])->toBe(1)
            ->and((float) $json['totals']['salesTotal'])->toEqual((float) $day['salesTotal']);
    });

    it('leaves out the empty days when emptyPeriods is false', function (): void {
        $json = reportOrdersGet('from=2003-01-14&to=2003-01-16&emptyPeriods=false')['json'];
        expect(array_column($json['periods'], 'period'))->toBe([REPORT_ORDERS_DAY]);
    });

    it('labels months and years', function (): void {
        $months = reportOrdersGet('from=2002-12-20&to=2003-02-10&periodType=month')['json'];
        expect(array_column($months['periods'], 'period'))->toBe(['2002-12', '2003-01', '2003-02'])
            ->and($months['periods'][1]['values']['ordersCount'])->toBe(1);

        $years = reportOrdersGet('from=2002-06-01&to=2003-06-30&periodType=year')['json'];
        expect(array_column($years['periods'], 'period'))->toBe(['2002', '2003'])
            ->and($years['periods'][1]['values']['ordersCount'])->toBe(1);
    });

    it('filters by order status', function (): void {
        $status = (string) $GLOBALS['reportOrdersOrder']->getStatus();
        $other = $status === 'complete' ? 'closed' : 'complete';

        $match = reportOrdersGet('from=2003-01-15&to=2003-01-15&orderStatuses=' . $status)['json'];
        expect($match['orderStatuses'])->toBe([$status])
            ->and($match['totals']['ordersCount'])->toBe(1);

        $noMatch = reportOrdersGet('from=2003-01-15&to=2003-01-15&orderStatuses=' . $other)['json'];
        expect($noMatch['totals']['ordersCount'])->toBe(0);
    });

    it('reads the table of the updated dates with dateBasis updated', function (): void {
        $json = reportOrdersGet('from=2003-01-15&to=2003-01-15&dateBasis=updated')['json'];
        expect($json['dateBasis'])->toBe('updated')
            ->and($json['totals']['ordersCount'])->toBe(1);
    });

    it('filters by store and website', function (): void {
        $store = reportOrdersGet('from=2003-01-15&to=2003-01-15&storeId=1')['json'];
        expect($store['scope'])->toBe(['storeIds' => [1], 'websiteId' => null, 'storeId' => 1])
            ->and($store['totals']['ordersCount'])->toBe(1);

        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            $other = reportOrdersGet('from=2003-01-15&to=2003-01-15&storeId=' . $otherStores[0])['json'];
            expect($other['totals']['ordersCount'])->toBe(0);
        }

        $website = reportOrdersGet('from=2003-01-15&to=2003-01-15&websiteId=1')['json'];
        expect($website['scope']['websiteId'])->toBe(1)
            ->and($website['scope']['storeIds'])->toContain(1)
            ->and($website['totals']['ordersCount'])->toBe(1);
    });

    it('puts an order on the day of the time zone of the default scope', function (): void {
        $store = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $timezone = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
        $product = ReportFixture::createProduct('report-orders-tz');

        // 11:30 UTC on 10 February is 00:30 on 11 February in Auckland (UTC+13 in summer)
        ReportFixture::placeOrder($product, 1, '2003-02-10 11:30:00');
        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Pacific/Auckland');
        try {
            ReportFixture::aggregate(['sales'], '2003-02-11', '2003-02-11');
        } finally {
            $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $timezone);
        }

        $json = reportOrdersGet('from=2003-02-10&to=2003-02-11')['json'];
        expect(array_column($json['periods'], 'period'))->toBe(['2003-02-10', '2003-02-11'])
            ->and($json['periods'][0]['values']['ordersCount'])->toBe(0)
            ->and($json['periods'][1]['values']['ordersCount'])->toBe(1);
    });
});
