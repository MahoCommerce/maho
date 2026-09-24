<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 products ordered report from the live order items, on days far in the past.
 *
 * @group read
 */

const REPORT_ORDERED_PATH = '/api/rest/v2/reports/products/ordered';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $first = ReportFixture::createProduct('report-ordered-a');
    $second = ReportFixture::createProduct('report-ordered-b');
    ReportFixture::placeOrder($first, 2, '2003-06-10 08:00:00');
    ReportFixture::placeOrder($first, 1, '2003-06-10 09:00:00');
    ReportFixture::placeOrder($second, 1, '2003-06-10 10:00:00');
    // 23:30 UTC in June is 00:30 of the next day in London
    ReportFixture::placeOrder($second, 4, '2003-06-10 23:30:00');
    $GLOBALS['reportOrdered'] = [$first, $second];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportOrderedGet(string $query): array
{
    $response = apiGet(REPORT_ORDERED_PATH . '?' . $query, adminToken());
    expect($response['status'])->toBe(200);
    return $response['json'];
}

describe('Products ordered report', function (): void {

    it('ranks the products of the range by ordered quantity', function (): void {
        [$first, $second] = $GLOBALS['reportOrdered'];
        if (Mage::getStoreConfig('general/locale/timezone', 0) !== 'Europe/London') {
            $this->markTestSkipped('The expected days need the time zone Europe/London');
        }
        $json = reportOrderedGet('from=2003-06-10&to=2003-06-10');
        expect($json['report'])->toBe('products-ordered')
            ->and($json['limit'])->toBe(20)
            ->and($json['totalItems'])->toBe(2)
            ->and($json['member'][0])->toBe([
                'rank' => 1,
                'productId' => (int) $first->getId(),
                'sku' => $first->getSku(),
                'name' => $first->getName(),
                'qtyOrdered' => 3,
            ])
            ->and($json['member'][1]['productId'])->toBe((int) $second->getId())
            ->and($json['member'][1]['qtyOrdered'])->toBe(1);

        $nextDay = reportOrderedGet('from=2003-06-11&to=2003-06-11');
        expect($nextDay['member'])->toHaveCount(1)
            ->and($nextDay['member'][0]['qtyOrdered'])->toBe(4);

        $both = reportOrderedGet('from=2003-06-10&to=2003-06-11&limit=1');
        expect($both['member'])->toHaveCount(1)
            ->and($both['member'][0]['productId'])->toBe((int) $second->getId())
            ->and($both['member'][0]['qtyOrdered'])->toBe(5);
    });

    it('filters by store and rejects a limit out of range', function (): void {
        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            expect(reportOrderedGet('from=2003-06-10&to=2003-06-11&storeId=' . $otherStores[0])['member'])->toBe([]);
        }
        expect(reportOrderedGet('from=2003-06-10&to=2003-06-11&storeId=1')['totalItems'])->toBe(2)
            ->and(apiGet(REPORT_ORDERED_PATH . '?from=2003-06-10&to=2003-06-11&limit=101', adminToken())['status'])->toBe(400)
            ->and(apiGet(REPORT_ORDERED_PATH . '?from=2003-06-10&to=2003-06-11&limit=0', adminToken())['status'])->toBe(400);
    });

    it('applies the admin ACL of the report', function (): void {
        $token = adminTokenWithAcl(['admin/report/products/sold'], 'apitest_reports_ordered');
        expect(apiGet(REPORT_ORDERED_PATH . '?from=2003-06-10&to=2003-06-10', $token)['status'])->toBe(200)
            ->and(apiGet('/api/rest/v2/reports/products/low-stock', $token)['status'])->toBe(403);
    });
});
