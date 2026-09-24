<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 search terms report.
 *
 * @group read
 */

const REPORT_SEARCH_PATH = '/api/rest/v2/reports/search-terms';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $tag = 'reportfixture' . substr(uniqid(), -6);
    $GLOBALS['reportSearch'] = [
        'tag' => $tag,
        'popular' => ReportFixture::addSearchTerm($tag . ' popular', 3, 90, '2003-09-01 10:00:00'),
        'results' => ReportFixture::addSearchTerm($tag . ' results', 40, 5, '2003-09-02 10:00:00'),
        'recent' => ReportFixture::addSearchTerm($tag . ' recent', 1, 1, '2003-09-03 10:00:00'),
    ];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportSearchIds(string $query): array
{
    $response = apiGet(REPORT_SEARCH_PATH . '?search=' . $GLOBALS['reportSearch']['tag'] . $query, adminToken());
    expect($response['status'])->toBe(200);
    return array_column($response['json']['member'], 'id');
}

describe('Search terms report', function (): void {

    it('lists the matching terms with the sort of the request', function (): void {
        $terms = $GLOBALS['reportSearch'];
        expect(reportSearchIds(''))->toBe([$terms['popular'], $terms['results'], $terms['recent']])
            ->and(reportSearchIds('&sort=results'))->toBe([$terms['results'], $terms['popular'], $terms['recent']])
            ->and(reportSearchIds('&sort=updatedAt'))->toBe([$terms['recent'], $terms['results'], $terms['popular']])
            ->and(reportSearchIds('%20popular'))->toBe([$terms['popular']]);

        $json = apiGet(REPORT_SEARCH_PATH . '?search=' . $terms['tag'] . '&pageSize=1&page=2', adminToken())['json'];
        expect($json['totalItems'])->toBe(3)
            ->and($json['page'])->toBe(2)
            ->and($json['member'])->toHaveCount(1)
            ->and($json['member'][0])->toBe([
                'id' => $terms['results'],
                'queryText' => $terms['tag'] . ' results',
                'storeId' => 1,
                'results' => 40,
                'uses' => 5,
                'updatedAt' => '2003-09-02T10:00:00+00:00',
            ]);
    });

    it('filters by store and rejects an unknown sort', function (): void {
        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            expect(reportSearchIds('&storeId=' . $otherStores[0]))->toBe([]);
        }
        expect(apiGet(REPORT_SEARCH_PATH . '?sort=name', adminToken())['status'])->toBe(400);
    });
});
