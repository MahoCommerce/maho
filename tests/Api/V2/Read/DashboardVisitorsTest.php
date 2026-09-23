<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 visitor data of the dashboard, from fixture visits in a store view without other visits.
 *
 * @group read
 */

const DASHBOARD_VISITORS_PATH = '/api/rest/v2/dashboard/visitors';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $storeIds = array_map(intval(...), array_keys(Mage::app()->getStores()));
    $storeId = max($storeIds);
    $visits = (int) ReportFixture::adapter()->fetchOne(
        ReportFixture::adapter()->select()->from(ReportFixture::table('log/visitor'), [new Maho\Db\Expr('COUNT(*)')])->where('store_id = ?', $storeId),
    );
    $GLOBALS['dashboardVisitors'] = ['storeId' => $storeId, 'clean' => $visits === 0];
    if ($visits !== 0) {
        return;
    }
    $now = gmdate('Y-m-d H:i:s', time() - 60);
    ReportFixture::addVisit(
        $storeId,
        $now,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
        'it-IT,it;q=0.9',
        'https://www.example.org/blog',
        '10.0.0.7',
        ['http://shop.example.test/a', 'http://shop.example.test/b'],
    );
    ReportFixture::addVisit(
        $storeId,
        $now,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'de-DE,de;q=0.9',
        '',
        '10.0.0.8',
        ['http://shop.example.test/a'],
    );
    // An earlier visit from the address of the second visit makes it a returning visitor
    ReportFixture::addVisit($storeId, gmdate('Y-m-d H:i:s', time() - 10 * 86400), 'Mozilla/5.0', 'de-DE', '', '10.0.0.8', []);
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

describe('Dashboard visitors', function (): void {

    it('returns the visitor tabs of the dashboard for one store view', function (): void {
        if (!Mage::helper('log')->isVisitorLogEnabled()) {
            $this->markTestSkipped('The visitor log is off');
        }
        if (!$GLOBALS['dashboardVisitors']['clean']) {
            $this->markTestSkipped('The last store view already has visits');
        }
        $response = apiGet(DASHBOARD_VISITORS_PATH . '?days=1&storeId=' . $GLOBALS['dashboardVisitors']['storeId'], adminToken());
        expect($response['status'])->toBe(200);
        $json = $response['json'];

        expect($json['enabled'])->toBeTrue()
            ->and($json['days'])->toBe(1)
            ->and($json['summary']['today'])->toBe(2)
            ->and($json['summary']['sessions'])->toBe(2)
            ->and($json['summary']['averagePages'])->toEqual(1.5)
            ->and($json['summary']['bounceRate'])->toEqual(50.0)
            ->and($json['trend'])->toHaveCount(30)
            ->and($json['trend'][29]['date'])->toBe(gmdate('Y-m-d'))
            ->and(array_sum($json['devices']['types']))->toBe(2)
            ->and(array_column($json['devices']['browsers'], 'visitors', 'name'))->toEqualCanonicalizing(['Firefox' => 1, 'Chrome' => 1])
            ->and($json['engagement'])->toBe(['visitors' => 2, 'loggedIn' => 0, 'loginRate' => 0, 'new' => 1, 'returning' => 1])
            ->and($json['entryPages'])->toBe([['url' => 'http://shop.example.test/a', 'visits' => 2]])
            ->and(array_column($json['exitPages'], 'exits', 'url'))->toEqualCanonicalizing(['http://shop.example.test/a' => 1, 'http://shop.example.test/b' => 1])
            ->and($json['topPages'][0])->toBe(['url' => 'http://shop.example.test/a', 'views' => 2])
            ->and($json['languages']['total'])->toBe(2)
            ->and(array_column($json['languages']['languages'], 'visitors', 'code'))->toEqualCanonicalizing(['it' => 1, 'de' => 1])
            ->and(array_column($json['trafficSources'], 'visitors', 'source'))->toEqualCanonicalizing(['www.example.org' => 1, 'direct' => 1]);
    });

    it('returns only the listed sections and checks the query', function (): void {
        $json = apiGet(DASHBOARD_VISITORS_PATH . '?sections=engagement,topPages', adminToken())['json'];
        if ($json['enabled']) {
            expect(array_keys($json))->toBe(['enabled', 'days', 'scope', 'engagement', 'topPages']);
        }
        expect(apiGet(DASHBOARD_VISITORS_PATH . '?sections=nope', adminToken())['status'])->toBe(400)
            ->and(apiGet(DASHBOARD_VISITORS_PATH . '?days=0', adminToken())['status'])->toBe(400)
            ->and(apiGet(DASHBOARD_VISITORS_PATH . '?days=91', adminToken())['status'])->toBe(400)
            ->and(apiGet(DASHBOARD_VISITORS_PATH, serviceToken(['dashboard/read']))['status'])->toBe(200)
            ->and(apiGet(DASHBOARD_VISITORS_PATH, serviceToken(['reports/read']))['status'])->toBe(403);

        $website = null;
        foreach (Mage::app()->getWebsites() as $candidate) {
            if (count($candidate->getStoreIds()) > 1) {
                $website = (int) $candidate->getId();
                break;
            }
        }
        if ($website !== null) {
            expect(apiGet(DASHBOARD_VISITORS_PATH . '?websiteId=' . $website, adminToken())['status'])->toBe(400);
        }
    });
});
