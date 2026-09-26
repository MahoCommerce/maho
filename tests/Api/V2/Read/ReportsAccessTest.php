<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 access rules and query validation of the reports and the dashboard.
 *
 * @group read
 */

const REPORTS_ACCESS_ORDERS = '/api/rest/v2/reports/sales/orders?from=2003-01-01&to=2003-01-02';
const REPORTS_ACCESS_BESTSELLERS = '/api/rest/v2/reports/products/bestsellers?from=2003-01-01&to=2003-01-02';
const REPORTS_ACCESS_STATISTICS = '/api/rest/v2/reports/statistics';
const REPORTS_ACCESS_DASHBOARD = '/api/rest/v2/dashboard?sections=totals';

beforeAll(function (): void {
    ReportFixture::snapshot();
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

/**
 * A website that does not have store 1, or null when there is none.
 */
function reportsAccessForeignWebsiteId(): ?int
{
    $ownWebsite = (int) Mage::app()->getStore(1)->getWebsiteId();
    foreach (Mage::app()->getWebsites() as $website) {
        if ((int) $website->getId() !== $ownWebsite && $website->getStoreIds() !== []) {
            return (int) $website->getId();
        }
    }
    return null;
}

describe('Report access', function (): void {

    it('needs a token', function (): void {
        expect(apiGet(REPORTS_ACCESS_ORDERS)['status'])->toBe(401)
            ->and(apiGet(REPORTS_ACCESS_DASHBOARD)['status'])->toBe(401);
    });

    it('applies the admin ACL of each report', function (): void {
        $sales = adminTokenWithAcl(['admin/report/salesroot/sales'], 'apitest_reports_sales');
        expect(apiGet(REPORTS_ACCESS_ORDERS, $sales)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_BESTSELLERS, $sales)['status'])->toBe(403)
            ->and(apiGet(REPORTS_ACCESS_STATISTICS, $sales)['status'])->toBe(403)
            ->and(apiGet(REPORTS_ACCESS_DASHBOARD, $sales)['status'])->toBe(403);

        $dashboard = adminTokenWithAcl(['admin/dashboard'], 'apitest_reports_dashboard');
        expect(apiGet(REPORTS_ACCESS_DASHBOARD, $dashboard)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_ORDERS, $dashboard)['status'])->toBe(403)
            ->and(apiPost(REPORTS_ACCESS_STATISTICS . '/refresh', ['reports' => ['viewed']], $dashboard)['status'])->toBe(403);
    });

    it('gives service accounts the reports and dashboard permissions', function (): void {
        $reports = serviceToken(['reports/read']);
        expect(apiGet(REPORTS_ACCESS_ORDERS, $reports)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_BESTSELLERS, $reports)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_STATISTICS, $reports)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_DASHBOARD, $reports)['status'])->toBe(403)
            ->and(apiPost(REPORTS_ACCESS_STATISTICS . '/refresh', ['reports' => ['viewed']], $reports)['status'])->toBe(403);

        $refresh = serviceToken(['reports/read', 'reports/refresh']);
        expect(apiPost(REPORTS_ACCESS_STATISTICS . '/refresh', ['reports' => ['viewed']], $refresh)['status'])->toBe(200);

        $dashboard = serviceToken(['dashboard/read']);
        expect(apiGet(REPORTS_ACCESS_DASHBOARD, $dashboard)['status'])->toBe(200)
            ->and(apiGet(REPORTS_ACCESS_ORDERS, $dashboard)['status'])->toBe(403);

        $other = serviceToken(['products/read']);
        expect(apiGet(REPORTS_ACCESS_ORDERS, $other)['status'])->toBe(403)
            ->and(apiGet(REPORTS_ACCESS_DASHBOARD, $other)['status'])->toBe(403)
            ->and(apiGet(REPORTS_ACCESS_STATISTICS, $other)['status'])->toBe(403);
    });

    it('keeps a token with a store restriction in its stores', function (): void {
        $token = serviceToken(['all'], [1]);

        $own = apiGet(REPORTS_ACCESS_ORDERS, $token);
        expect($own['status'])->toBe(200)
            ->and($own['json']['scope']['storeIds'])->toBe([1]);
        expect(apiGet(REPORTS_ACCESS_DASHBOARD, $token)['json']['scope']['storeIds'])->toBe([1]);

        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            expect(apiGet(REPORTS_ACCESS_ORDERS . '&storeId=' . $otherStores[0], $token)['status'])->toBe(403)
                ->and(apiGet(REPORTS_ACCESS_DASHBOARD . '&storeId=' . $otherStores[0], $token)['status'])->toBe(403);
        }
        $foreignWebsite = reportsAccessForeignWebsiteId();
        if ($foreignWebsite !== null) {
            expect(apiGet(REPORTS_ACCESS_BESTSELLERS . '&websiteId=' . $foreignWebsite, $token)['status'])->toBe(403);
        }
        expect(apiPost(REPORTS_ACCESS_STATISTICS . '/refresh', ['reports' => ['viewed']], $token)['status'])->toBe(403);
    });

    it('rejects a query that is not valid', function (): void {
        $token = adminToken();
        $base = '/api/rest/v2/reports/sales/orders?';
        $invalid = [
            'from=2003-01-01&to=2003-01-02&periodType=week',
            'from=2003-01-02&to=2003-01-01',
            'from=2000-01-01&to=2003-01-01',
            'from=2003-01-01&to=2003-01-02&orderStatuses=nope',
            'from[]=2003-01-01&to=2003-01-02',
            'from=2003-01-01&to=2003-01-02&storeId=1&websiteId=1',
            'to=2003-01-02',
            'from=2003-01-01',
            'from=2003-02-30&to=2003-03-01',
            'from=01/01/2003&to=2003-03-01',
            'from=2003-01-01&to=2003-01-02&dateBasis=invoice',
            'from=2003-01-01&to=2003-01-02&storeId=999999',
            'from=2003-01-01&to=2003-01-02&websiteId=999999',
            'from=2003-01-01&to=2003-01-02&emptyPeriods=maybe',
        ];
        foreach ($invalid as $query) {
            expect(apiGet($base . $query, $token)['status'])->toBe(400, $query);
        }
        expect(apiGet($base . 'from=2000-01-01&to=2003-01-01&periodType=month', $token)['status'])->toBe(200);
    });
});
