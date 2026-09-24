<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 statistics of the aggregated reports: the list and the refresh.
 *
 * @group read
 */

const REPORT_STATISTICS_PATH = '/api/rest/v2/reports/statistics';

beforeAll(function (): void {
    ReportFixture::snapshot();
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportStatisticsUpdatedAt(array $json, string $code): ?string
{
    foreach ($json['member'] as $member) {
        if ($member['code'] === $code) {
            return $member['updatedAt'];
        }
    }
    return null;
}

function reportStatisticsTodayOrders(): int
{
    $today = Mage::app()->getLocale()->utcToStore(Mage_Core_Model_App::ADMIN_STORE_ID)->format('Y-m-d');
    $response = apiGet('/api/rest/v2/reports/sales/orders?from=' . $today . '&to=' . $today, adminToken());
    expect($response['status'])->toBe(200);
    return (int) $response['json']['totals']['ordersCount'];
}

/**
 * @return list<array<string, mixed>>
 */
function reportStatisticsQueueRows(): array
{
    return array_values(array_filter(fetchQueueRows(), static fn(array $row): bool => $row['queue'] === Mage_Reports_Model_Queue_RefreshStatistics::QUEUE_NAME));
}

describe('Report statistics', function (): void {

    it('lists the eight aggregated reports', function (): void {
        $response = apiGet(REPORT_STATISTICS_PATH, adminToken());
        expect($response['status'])->toBe(200)
            ->and($response['json']['totalItems'])->toBe(8)
            ->and(array_column($response['json']['member'], 'code'))
            ->toBe(['sales', 'tax', 'shipping', 'invoiced', 'refunded', 'coupons', 'bestsellers', 'viewed']);
        foreach ($response['json']['member'] as $member) {
            expect($member['label'])->toBeString()->not->toBe('')
                ->and(array_key_exists('updatedAt', $member))->toBeTrue();
        }
    });

    it('refreshes the recent statistics at once, and the report then has an order placed now', function (): void {
        ReportFixture::adapter()->update(ReportFixture::table('core/flag'), ['last_update' => '2001-01-01 00:00:00'], ['flag_code = ?' => Mage_Reports_Model_Flag::REPORT_ORDER_FLAG_CODE]);

        $first = apiPost(REPORT_STATISTICS_PATH . '/refresh', ['mode' => 'recent', 'reports' => ['sales']], adminToken());
        expect($first['status'])->toBe(200)
            ->and($first['json']['queued'])->toBeFalse()
            ->and($first['json']['reports'])->toBe(['sales'])
            ->and(reportStatisticsUpdatedAt($first['json'], 'sales'))->not->toBe('2001-01-01T00:00:00+00:00');
        $before = reportStatisticsTodayOrders();

        ReportFixture::placeOrder(ReportFixture::createProduct('report-statistics'), 1);
        expect(reportStatisticsTodayOrders())->toBe($before);

        $second = apiPost(REPORT_STATISTICS_PATH . '/refresh', ['reports' => ['sales']], adminToken());
        expect($second['status'])->toBe(200)
            ->and($second['json']['mode'])->toBe('recent')
            ->and(reportStatisticsTodayOrders())->toBe($before + 1);
    });

    it('queues a lifetime refresh once per report and runs it in the queue handler', function (): void {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            $this->markTestSkipped('Maho_Queue is disabled');
        }
        ReportFixture::adapter()->update(ReportFixture::table('core/flag'), ['last_update' => '2001-01-01 00:00:00'], ['flag_code = ?' => Mage_Reports_Model_Flag::REPORT_PRODUCT_VIEWED_FLAG_CODE]);
        expect(reportStatisticsQueueRows())->toBe([]);

        $first = apiPost(REPORT_STATISTICS_PATH . '/refresh', ['mode' => 'lifetime', 'reports' => ['viewed']], adminToken());
        expect($first['status'])->toBe(202)
            ->and($first['json']['queued'])->toBeTrue()
            ->and($first['json']['reports'])->toBe(['viewed'])
            ->and($first['json']['alreadyQueued'])->toBe([]);
        $rows = reportStatisticsQueueRows();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['message_class'])->toBe(Mage_Reports_Model_Queue_RefreshStatistics::class)
            ->and($rows[0]['dedupe_key'])->toBe('reports_lifetime_viewed');

        $second = apiPost(REPORT_STATISTICS_PATH . '/refresh', ['mode' => 'lifetime', 'reports' => ['viewed']], adminToken());
        expect($second['status'])->toBe(202)
            ->and($second['json']['alreadyQueued'])->toBe(['viewed'])
            ->and(reportStatisticsQueueRows())->toHaveCount(1);

        (new Mage_Reports_Model_Queue_RefreshStatisticsHandler())(new Mage_Reports_Model_Queue_RefreshStatistics('viewed'));
        $list = apiGet(REPORT_STATISTICS_PATH, adminToken());
        expect(reportStatisticsUpdatedAt($list['json'], 'viewed'))->not->toBe('2001-01-01T00:00:00+00:00');
    });

    it('rejects an unknown mode or report code', function (): void {
        expect(apiPost(REPORT_STATISTICS_PATH . '/refresh', ['mode' => 'all'], adminToken())['status'])->toBe(400)
            ->and(apiPost(REPORT_STATISTICS_PATH . '/refresh', ['reports' => ['nope']], adminToken())['status'])->toBe(400)
            ->and(apiPost(REPORT_STATISTICS_PATH . '/refresh', ['reports' => 'sales'], adminToken())['status'])->toBe(400);
    });
});
