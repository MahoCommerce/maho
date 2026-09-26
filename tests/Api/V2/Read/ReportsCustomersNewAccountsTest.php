<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 new accounts report from the live customer table, on days far in the past.
 *
 * @group read
 */

const REPORT_ACCOUNTS_PATH = '/api/rest/v2/reports/customers/new-accounts';

beforeAll(function (): void {
    ReportFixture::snapshot();
    ReportFixture::createCustomer('accounts-a', '2003-07-01 10:00:00');
    ReportFixture::createCustomer('accounts-b', '2003-07-01 11:00:00');
    ReportFixture::createCustomer('accounts-c', '2003-07-02 10:00:00');
    // 23:30 UTC in July is 00:30 of the next day in London
    ReportFixture::createCustomer('accounts-d', '2003-07-02 23:30:00');
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

describe('New accounts report', function (): void {

    it('counts the new accounts of each day in the time zone of the default scope', function (): void {
        if (Mage::getStoreConfig('general/locale/timezone', 0) !== 'Europe/London') {
            $this->markTestSkipped('The expected days need the time zone Europe/London');
        }
        $response = apiGet(REPORT_ACCOUNTS_PATH . '?from=2003-06-30&to=2003-07-03', adminToken());
        expect($response['status'])->toBe(200);
        $json = $response['json'];
        expect($json['report'])->toBe('customers-new-accounts')
            ->and($json)->not->toHaveKey('statistics')
            ->and($json['totals'])->toBe(['accounts' => 4])
            ->and(array_column($json['periods'], 'values', 'period'))->toBe([
                '2003-06-30' => ['accounts' => 0],
                '2003-07-01' => ['accounts' => 2],
                '2003-07-02' => ['accounts' => 1],
                '2003-07-03' => ['accounts' => 1],
            ]);
    });

    it('counts per month and filters by store', function (): void {
        $months = apiGet(REPORT_ACCOUNTS_PATH . '?from=2003-06-01&to=2003-08-31&periodType=month&emptyPeriods=false', adminToken())['json'];
        expect($months['periods'])->toBe([['period' => '2003-07', 'values' => ['accounts' => 4]]]);

        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            $other = apiGet(REPORT_ACCOUNTS_PATH . '?from=2003-07-01&to=2003-07-03&storeId=' . $otherStores[0], adminToken())['json'];
            expect($other['totals'])->toBe(['accounts' => 0]);
        }
    });
});
