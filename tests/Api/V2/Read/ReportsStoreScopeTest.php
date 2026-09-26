<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 reports for a token restricted to store 1, and for a website without store views.
 *
 * @group read
 */

const REPORTS_SCOPE_WEBSITE_CODE = 'pest_reports_empty';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $otherStoreIds = array_values(array_diff(array_map(intval(...), array_keys(Mage::app()->getStores())), [1]));
    $product = ReportFixture::createProduct('report-scope', 15.0);
    $customer = ReportFixture::createCustomer('scope');
    ReportFixture::createCart($product, 1);
    ReportFixture::placeOrder($product, 1, null, true, $customer);
    $movedOrder = null;
    if ($otherStoreIds !== []) {
        // The second order of the customer belongs to a store that the restricted token cannot read
        $movedOrder = ReportFixture::placeOrder($product, 1, null, true, $customer);
        foreach (['sales/order' => 'entity_id', 'sales/order_item' => 'order_id'] as $table => $key) {
            ReportFixture::adapter()->update(ReportFixture::table($table), ['store_id' => $otherStoreIds[0]], [$key . ' = ?' => $movedOrder->getId()]);
        }
    }

    $website = Mage::getModel('core/website')->load(REPORTS_SCOPE_WEBSITE_CODE, 'code');
    if (!$website->getId()) {
        $website->setCode(REPORTS_SCOPE_WEBSITE_CODE)->setName('Pest Reports Empty Website')->setSortOrder(99)->save();
    }
    Mage::app()->cleanCache();

    $GLOBALS['reportsScope'] = [
        'product' => $product,
        'customer' => $customer,
        'hasOtherStore' => $movedOrder !== null,
        'websiteId' => (int) $website->getId(),
    ];
});

afterAll(function (): void {
    ReportFixture::restore();
    Mage::register('isSecureArea', true);
    try {
        $website = Mage::getModel('core/website')->load(REPORTS_SCOPE_WEBSITE_CODE, 'code');
        if ($website->getId()) {
            $website->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
    Mage::app()->cleanCache();
    cleanupTestData();
});

/**
 * @return array<string, mixed>
 */
function reportsScopeGet(string $path, string $token): array
{
    $response = apiGet('/api/rest/v2' . $path, $token);
    expect($response['status'])->toBe(200);
    return $response['json'];
}

/**
 * @return array<string, mixed>|null the row of the fixture product in the products in carts report
 */
function reportsScopeCartRow(string $token): ?array
{
    $productId = (int) $GLOBALS['reportsScope']['product']->getId();
    for ($page = 1; $page <= 50; $page++) {
        $json = reportsScopeGet('/reports/carts/products?pageSize=100&page=' . $page, $token);
        foreach ($json['member'] as $row) {
            if ($row['productId'] === $productId) {
                return $row;
            }
        }
        if ($page * 100 >= $json['totalItems']) {
            break;
        }
    }
    return null;
}

describe('Reports with a store scope', function (): void {

    it('counts only the order items of the allowed stores in the products in carts report', function (): void {
        $expectedAll = $GLOBALS['reportsScope']['hasOtherStore'] ? 2 : 1;
        expect(reportsScopeCartRow(adminToken()))->toMatchArray(['orders' => $expectedAll])
            ->and(reportsScopeCartRow(serviceToken(['reports/read'], [1])))->toMatchArray(['orders' => 1]);
    });

    it('counts only the orders of the allowed stores for the new customers of the dashboard', function (): void {
        $customerId = (int) $GLOBALS['reportsScope']['customer']->getId();
        $expectedAll = $GLOBALS['reportsScope']['hasOtherStore'] ? 2 : 1;
        $rows = fn(string $token): array => array_column(
            reportsScopeGet('/dashboard?sections=newCustomers', $token)['newCustomers'],
            null,
            'id',
        );

        expect($rows(adminToken())[$customerId] ?? null)->toMatchArray(['ordersCount' => $expectedAll, 'averageOrder' => 15, 'totalOrders' => 15 * $expectedAll])
            ->and($rows(serviceToken(['dashboard/read'], [1]))[$customerId] ?? null)->toMatchArray(['ordersCount' => 1, 'averageOrder' => 15, 'totalOrders' => 15]);
    });

    it('returns null for the online visitors of one store view', function (): void {
        $all = reportsScopeGet('/dashboard/visitors?sections=summary', adminToken());
        if (!$all['enabled']) {
            $this->markTestSkipped('The visitor log is disabled');
        }
        expect($all['summary']['online'])->toBeInt()
            ->and(reportsScopeGet('/dashboard/visitors?sections=summary', serviceToken(['dashboard/read'], [1]))['summary']['online'])->toBeNull()
            ->and(reportsScopeGet('/dashboard/visitors?sections=summary&storeId=1', adminToken())['summary']['online'])->toBeNull();
    });

    it('returns empty reports for a website without store views', function (): void {
        $websiteId = $GLOBALS['reportsScope']['websiteId'];
        $scope = ['storeIds' => [-1], 'websiteId' => $websiteId, 'storeId' => null];

        $dashboard = reportsScopeGet('/dashboard?sections=totals,lastOrders,newCustomers&websiteId=' . $websiteId, adminToken());
        expect($dashboard['scope'])->toBe($scope)
            ->and($dashboard['totals']['orders'])->toBe(0)
            ->and($dashboard['lastOrders'])->toBe([])
            ->and($dashboard['newCustomers'])->toBe([]);

        $lowStock = reportsScopeGet('/reports/products/low-stock?websiteId=' . $websiteId, adminToken());
        expect($lowStock['totalItems'])->toBe(0)
            ->and($lowStock['member'])->toBe([]);

        $visitors = reportsScopeGet('/dashboard/visitors?websiteId=' . $websiteId, adminToken());
        expect($visitors['scope'])->toBe($scope);
        if ($visitors['enabled']) {
            expect($visitors['summary'])->toMatchArray(['online' => null, 'today' => 0, 'sessions' => 0])
                ->and($visitors['trend'])->toHaveCount(30)
                ->and(array_sum(array_column($visitors['trend'], 'visitors')))->toBe(0)
                ->and($visitors['devices'])->toBe(['types' => ['desktop' => 0, 'tablet' => 0, 'mobile' => 0], 'browsers' => []])
                ->and($visitors['topPages'])->toBe([]);
        }
    });
});
