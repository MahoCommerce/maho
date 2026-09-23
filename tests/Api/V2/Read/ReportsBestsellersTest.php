<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 bestsellers report on a day far in the past.
 *
 * @group read
 */

const REPORT_BESTSELLERS_PATH = '/api/rest/v2/reports/products/bestsellers';
const REPORT_BESTSELLERS_DAY = '2003-03-12';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $first = ReportFixture::createProduct('report-best-a', 12.5);
    $second = ReportFixture::createProduct('report-best-b', 20.0);
    ReportFixture::placeOrder($first, 3, REPORT_BESTSELLERS_DAY . ' 10:00:00');
    ReportFixture::placeOrder($second, 1, REPORT_BESTSELLERS_DAY . ' 11:00:00');
    $GLOBALS['reportBestsellers'] = [$first, $second];
    ReportFixture::aggregate(['bestsellers'], REPORT_BESTSELLERS_DAY, REPORT_BESTSELLERS_DAY);
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

/**
 * @return list<array<string, mixed>>
 */
function reportBestsellerRows(string $query, string $period): array
{
    $response = apiGet(REPORT_BESTSELLERS_PATH . '?' . $query, adminToken());
    expect($response['status'])->toBe(200);
    foreach ($response['json']['periods'] as $entry) {
        if ($entry['period'] === $period) {
            return $entry['rows'];
        }
    }
    return [];
}

describe('Bestsellers report', function (): void {

    it('ranks the products of the day by ordered quantity', function (): void {
        [$first, $second] = $GLOBALS['reportBestsellers'];
        $response = apiGet(REPORT_BESTSELLERS_PATH . '?from=2003-03-11&to=2003-03-12', adminToken());
        expect($response['status'])->toBe(200);
        $json = $response['json'];

        expect($json['report'])->toBe('products-bestsellers')
            ->and($json['statistics']['code'])->toBe('bestsellers')
            ->and(array_column($json['periods'], 'period'))->toBe(['2003-03-11', REPORT_BESTSELLERS_DAY])
            ->and($json['periods'][0]['rows'])->toBe([])
            ->and((float) $json['totals']['qtyOrdered'])->toEqual(4.0);

        $rows = $json['periods'][1]['rows'];
        expect($rows)->toHaveCount(2)
            ->and($rows[0]['rank'])->toBe(1)
            ->and($rows[0]['productId'])->toBe((int) $first->getId())
            ->and($rows[0]['sku'])->toBe($first->getSku())
            ->and($rows[0]['name'])->toBe($first->getName())
            ->and((float) $rows[0]['price'])->toEqual(12.5)
            ->and((float) $rows[0]['qtyOrdered'])->toEqual(3.0)
            ->and($rows[1]['rank'])->toBe(2)
            ->and($rows[1]['productId'])->toBe((int) $second->getId())
            ->and((float) $rows[1]['qtyOrdered'])->toEqual(1.0);
    });

    it('ranks the products of a month and of a year, complete or cut by the range', function (): void {
        [$first] = $GLOBALS['reportBestsellers'];
        $cases = [
            ['from=2003-03-01&to=2003-03-31&periodType=month', '2003-03'],
            ['from=2003-03-05&to=2003-03-20&periodType=month', '2003-03'],
            ['from=2003-01-01&to=2003-12-31&periodType=year', '2003'],
            ['from=2003-02-01&to=2003-06-30&periodType=year', '2003'],
        ];
        foreach ($cases as [$query, $period]) {
            $rows = reportBestsellerRows($query, $period);
            expect($rows)->toHaveCount(2)
                ->and($rows[0]['productId'])->toBe((int) $first->getId())
                ->and((float) $rows[0]['qtyOrdered'])->toEqual(3.0);
        }
    });

    it('has no rows for a store without the orders', function (): void {
        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores === []) {
            $this->markTestSkipped('The store has one store view');
        }
        expect(reportBestsellerRows('from=2003-03-12&to=2003-03-12&storeId=' . $otherStores[0], REPORT_BESTSELLERS_DAY))->toBe([])
            ->and(reportBestsellerRows('from=2003-03-12&to=2003-03-12&storeId=1', REPORT_BESTSELLERS_DAY))->toHaveCount(2);
    });
});
