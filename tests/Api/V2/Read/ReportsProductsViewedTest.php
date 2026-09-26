<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 most viewed products report on a day far in the past.
 *
 * @group read
 */

const REPORT_VIEWED_DAY = '2003-05-06';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $first = ReportFixture::createProduct('report-viewed-a', 15.0);
    $second = ReportFixture::createProduct('report-viewed-b', 25.0);
    ReportFixture::addProductViews((int) $first->getId(), 3, REPORT_VIEWED_DAY . ' 10:00:00');
    ReportFixture::addProductViews((int) $second->getId(), 1, REPORT_VIEWED_DAY . ' 11:00:00');
    ReportFixture::aggregate(['viewed'], REPORT_VIEWED_DAY, REPORT_VIEWED_DAY);
    $GLOBALS['reportViewed'] = [$first, $second];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

describe('Most viewed products report', function (): void {

    it('ranks the products of the day by views', function (): void {
        [$first, $second] = $GLOBALS['reportViewed'];
        $response = apiGet('/api/rest/v2/reports/products/viewed?from=2003-05-05&to=2003-05-06', adminToken());
        expect($response['status'])->toBe(200);
        $json = $response['json'];

        expect($json['report'])->toBe('products-viewed')
            ->and($json['statistics']['code'])->toBe('viewed')
            ->and($json['periods'][0]['rows'])->toBe([])
            ->and((int) $json['totals']['views'])->toBe(4);
        $rows = $json['periods'][1]['rows'];
        expect($rows)->toHaveCount(2)
            ->and($rows[0])->toBe([
                'rank' => 1,
                'productId' => (int) $first->getId(),
                'sku' => $first->getSku(),
                'name' => $first->getName(),
                'price' => 15,
                'views' => 3,
            ])
            ->and($rows[1]['productId'])->toBe((int) $second->getId())
            ->and($rows[1]['views'])->toBe(1);
    });

    it('ranks the products of a month and of a year', function (): void {
        [$first] = $GLOBALS['reportViewed'];
        foreach (['from=2003-05-01&to=2003-05-31&periodType=month', 'from=2003-05-02&to=2003-05-20&periodType=month', 'from=2003-01-01&to=2003-12-31&periodType=year'] as $query) {
            $json = apiGet('/api/rest/v2/reports/products/viewed?' . $query, adminToken())['json'];
            expect($json['periods'][0]['rows'][0]['productId'])->toBe((int) $first->getId())
                ->and($json['periods'][0]['rows'][0]['views'])->toBe(3);
        }
    });

    it('applies the admin ACL of the report', function (): void {
        $token = adminTokenWithAcl(['admin/report/products/viewed'], 'apitest_reports_viewed');
        expect(apiGet('/api/rest/v2/reports/products/viewed?from=2003-05-06&to=2003-05-06', $token)['status'])->toBe(200)
            ->and(apiGet('/api/rest/v2/reports/sales/tax?from=2003-05-06&to=2003-05-06', $token)['status'])->toBe(403);
    });
});
