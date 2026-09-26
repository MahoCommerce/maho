<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 dashboard with live data.
 *
 * @group read
 */

const DASHBOARD_PATH = '/api/rest/v2/dashboard';

beforeAll(function (): void {
    ReportFixture::snapshot();
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function dashboardGet(string $query = ''): array
{
    $response = apiGet(DASHBOARD_PATH . ($query !== '' ? '?' . $query : ''), adminToken());
    expect($response['status'])->toBe(200);
    return $response['json'];
}

describe('Dashboard', function (): void {

    it('counts an order placed now in the totals, the chart and the last orders', function (): void {
        if (Mage::getStoreConfigFlag('sales/dashboard/use_aggregated_data')) {
            $this->markTestSkipped('The dashboard uses the aggregated data');
        }
        $before = dashboardGet('period=24h&sections=totals');

        $customer = ReportFixture::createCustomer('dashboard');
        $order = ReportFixture::placeOrder(ReportFixture::createProduct('report-dashboard'), 2, customer: $customer);
        $rate = (float) $order->getBaseToGlobalRate();
        $revenue = ((float) $order->getBaseTotalInvoiced() - (float) $order->getBaseTaxInvoiced() - (float) $order->getBaseShippingInvoiced()) * $rate;

        $json = dashboardGet('period=24h');
        expect($json['source'])->toBe('live')
            ->and($json['currency'])->toBe(Mage::app()->getBaseCurrencyCode())
            ->and($json['totals']['orders'])->toBe($before['totals']['orders'] + 1)
            ->and((float) $json['totals']['revenue'])->toEqualWithDelta((float) $before['totals']['revenue'] + $revenue, 0.001)
            ->and($json['chart']['bucket'])->toBe('hour')
            ->and(array_sum(array_column($json['chart']['points'], 'orders')))->toBeGreaterThanOrEqual(1)
            ->and($json['lastOrders'][0]['id'])->toBe((int) $order->getId())
            ->and($json['lastOrders'][0]['incrementId'])->toBe($order->getIncrementId())
            ->and($json['lastOrders'][0]['itemsCount'])->toBe(1)
            ->and((float) $json['lastOrders'][0]['grandTotal'])->toEqualWithDelta((float) $order->getBaseGrandTotal() * $rate, 0.001)
            ->and($json['lastOrders'][0]['customerName'])->toBe('Report Dashboard')
            ->and($json['lastOrders'][0]['customerId'])->toBe((int) $customer->getId())
            ->and(array_column($json['newCustomers'], 'id'))->toContain((int) $customer->getId())
            ->and((float) $json['lifetime']['sales'])->toBeGreaterThanOrEqual($revenue);

        $points = array_column($json['chart']['points'], 'orders', 'label');
        $hour = Mage::app()->getLocale()->utcToStore(Mage_Core_Model_App::ADMIN_STORE_ID)->format('Y-m-d H:00');
        expect($points[$hour] ?? 0)->toBeGreaterThanOrEqual(1);

        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            $scoped = dashboardGet('sections=lastOrders&storeId=' . $otherStores[0]);
            expect(array_column($scoped['lastOrders'], 'id'))->not->toContain((int) $order->getId());
        }
    });

    it('has one chart point per bucket of each period', function (): void {
        $today = Mage::app()->getLocale()->utcToStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $monthStart = (int) Mage::getStoreConfig('reports/dashboard/mtd_start');
        [$yearStartMonth] = array_map(intval(...), explode(',', (string) Mage::getStoreConfig('reports/dashboard/ytd_start')));
        $expected = [
            '24h' => 25,
            '7d' => 7,
            '1m' => (int) $today->format('j') - $monthStart + 1,
            '3m' => 3,
            '6m' => 6,
            '1y' => (int) $today->format('n') - $yearStartMonth + 1,
            '2y' => (int) $today->format('n') - $yearStartMonth + 13,
        ];
        foreach ($expected as $period => $count) {
            $json = dashboardGet('sections=chart&period=' . $period);
            expect($json['period'])->toBe($period)
                ->and($json['chart']['points'])->toHaveCount($count);
        }
    });

    it('returns only the listed sections', function (): void {
        $json = dashboardGet('sections=totals,lastOrders');
        expect(array_keys($json))->toBe(['period', 'timezone', 'currency', 'source', 'scope', 'totals', 'lastOrders']);

        $all = dashboardGet();
        foreach (['lifetime', 'totals', 'chart', 'lastOrders', 'lastSearchTerms', 'topSearchTerms', 'bestsellers', 'mostViewed', 'newCustomers', 'topCustomers'] as $section) {
            expect($all)->toHaveKey($section);
        }
    });

    it('rejects an unknown period or section', function (): void {
        expect(apiGet(DASHBOARD_PATH . '?period=5d', adminToken())['status'])->toBe(400)
            ->and(apiGet(DASHBOARD_PATH . '?sections=totals,nope', adminToken())['status'])->toBe(400);
    });
});
