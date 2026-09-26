<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 low stock report.
 *
 * @group read
 */

const REPORT_LOW_STOCK_PATH = '/api/rest/v2/reports/products/low-stock';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $GLOBALS['reportLowStock'] = [
        'low' => ReportFixture::createProduct('report-low', 10.0, 0, ['qty' => 2, 'use_config_notify_stock_qty' => 0, 'notify_stock_qty' => 5]),
        'enough' => ReportFixture::createProduct('report-enough', 10.0, 0, ['qty' => 50, 'use_config_notify_stock_qty' => 0, 'notify_stock_qty' => 5]),
        'unmanaged' => ReportFixture::createProduct('report-unmanaged', 10.0, 0, ['qty' => 0, 'manage_stock' => 0, 'use_config_notify_stock_qty' => 0, 'notify_stock_qty' => 5]),
    ];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

/**
 * @return array<int, array<string, mixed>> the rows of all pages, by product ID
 */
function reportLowStockRows(string $query = ''): array
{
    $rows = [];
    for ($page = 1; $page <= 50; $page++) {
        $response = apiGet(REPORT_LOW_STOCK_PATH . '?pageSize=100&page=' . $page . $query, adminToken());
        expect($response['status'])->toBe(200);
        foreach ($response['json']['member'] as $row) {
            $rows[$row['productId']] = $row;
        }
        if ($page * 100 >= $response['json']['totalItems']) {
            break;
        }
    }
    return $rows;
}

describe('Low stock report', function (): void {

    it('lists the products below their notify quantity', function (): void {
        $products = $GLOBALS['reportLowStock'];
        $rows = reportLowStockRows();

        expect($rows)->toHaveKey((int) $products['low']->getId())
            ->and($rows)->not->toHaveKey((int) $products['enough']->getId())
            ->and($rows)->not->toHaveKey((int) $products['unmanaged']->getId())
            ->and($rows[(int) $products['low']->getId()])->toBe([
                'productId' => (int) $products['low']->getId(),
                'sku' => $products['low']->getSku(),
                'name' => $products['low']->getName(),
                'type' => 'simple',
                'qty' => 2,
                'notifyStockQty' => 5,
            ]);
    });

    it('pages the list and filters by website', function (): void {
        $first = apiGet(REPORT_LOW_STOCK_PATH . '?pageSize=1', adminToken())['json'];
        expect($first['report'])->toBe('products-low-stock')
            ->and($first['page'])->toBe(1)
            ->and($first['pageSize'])->toBe(1)
            ->and($first['member'])->toHaveCount(1)
            ->and($first['totalItems'])->toBeGreaterThanOrEqual(1);

        $low = (int) $GLOBALS['reportLowStock']['low']->getId();
        expect(reportLowStockRows('&websiteId=1'))->toHaveKey($low);
        foreach (Mage::app()->getWebsites() as $website) {
            if ((int) $website->getId() !== 1 && $website->getStoreIds() !== []) {
                expect(reportLowStockRows('&websiteId=' . $website->getId()))->not->toHaveKey($low);
                break;
            }
        }
    });
});
