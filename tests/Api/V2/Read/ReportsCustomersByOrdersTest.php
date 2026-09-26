<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 customer reports by orders total and by orders count, from the live orders on a day far in the past.
 *
 * @group read
 */

beforeAll(function (): void {
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('report-customers', 10.0);
    $many = ReportFixture::createCustomer('orders-many');
    $large = ReportFixture::createCustomer('orders-large');
    ReportFixture::placeOrder($product, 1, '2003-08-05 09:00:00', customer: $many);
    ReportFixture::placeOrder($product, 1, '2003-08-05 10:00:00', customer: $many);
    ReportFixture::placeOrder($product, 5, '2003-08-05 11:00:00', customer: $large);
    $GLOBALS['reportCustomers'] = ['many' => $many, 'large' => $large];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportCustomersGet(string $report, string $query = ''): array
{
    $response = apiGet('/api/rest/v2/reports/customers/' . $report . '?from=2003-08-05&to=2003-08-05' . $query, adminToken());
    expect($response['status'])->toBe(200);
    return $response['json'];
}

describe('Customer reports by orders', function (): void {

    it('ranks the customers by the total of their orders', function (): void {
        ['many' => $many, 'large' => $large] = $GLOBALS['reportCustomers'];
        $json = reportCustomersGet('by-orders-total');
        expect($json['report'])->toBe('customers-by-orders-total')
            ->and($json['currency'])->toBe(Mage::app()->getBaseCurrencyCode())
            ->and(array_column($json['member'], 'customerId'))->toBe([(int) $large->getId(), (int) $many->getId()])
            ->and($json['member'][0])->toBe([
                'rank' => 1,
                'customerId' => (int) $large->getId(),
                'name' => 'Report Orders-large',
                'ordersCount' => 1,
                'averageOrder' => 50,
                'totalOrders' => 50,
            ])
            ->and($json['member'][1]['ordersCount'])->toBe(2)
            ->and($json['member'][1]['totalOrders'])->toBe(20)
            ->and($json['member'][1]['averageOrder'])->toBe(10);
    });

    it('ranks the customers by the number of their orders', function (): void {
        ['many' => $many, 'large' => $large] = $GLOBALS['reportCustomers'];
        $json = reportCustomersGet('by-orders-count');
        expect($json['report'])->toBe('customers-by-orders-count')
            ->and(array_column($json['member'], 'customerId'))->toBe([(int) $many->getId(), (int) $large->getId()])
            ->and($json['member'][0]['rank'])->toBe(1)
            ->and($json['member'][0]['ordersCount'])->toBe(2);

        expect(reportCustomersGet('by-orders-count', '&limit=1')['member'])->toHaveCount(1);
    });

    it('applies the admin ACL of each report', function (): void {
        $token = adminTokenWithAcl(['admin/report/customers/totals'], 'apitest_reports_customers');
        expect(apiGet('/api/rest/v2/reports/customers/by-orders-total?from=2003-08-05&to=2003-08-05', $token)['status'])->toBe(200)
            ->and(apiGet('/api/rest/v2/reports/customers/by-orders-count?from=2003-08-05&to=2003-08-05', $token)['status'])->toBe(403)
            ->and(apiGet('/api/rest/v2/reports/customers/new-accounts?from=2003-08-05&to=2003-08-05', $token)['status'])->toBe(403);
    });
});
