<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('gives a valid SQL expression for a time zone with daylight saving time', function (): void {
    Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Europe/London');
    $resource = Mage::getResourceModel('sales/report_order');
    $table = $resource->getTable('sales/order');
    $expression = $resource->getStoreTZOffsetQuery(['o' => $table], 'o.created_at', '2025-01-01 00:00:00', '2025-12-31 23:59:59', Mage_Core_Model_App::ADMIN_STORE_ID);

    // Two offsets in the range give a CASE expression, and each END has a space before it
    expect($expression)->toContain('CASE WHEN')
        ->and(preg_match('/\SEND\b/', $expression))->toBe(0);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $select = $adapter->select()->from(['o' => $table], ['local' => new Maho\Db\Expr($expression)])->limit(1);
    expect(fn() => $adapter->fetchAll($select))->not->toThrow(Throwable::class);
});

it('keeps the time of the shifted value on request, for a report by hour', function (): void {
    Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Europe/London');
    $resource = Mage::getResourceModel('sales/report_order');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = ['o' => new Maho\Db\Expr("(SELECT '2025-07-01 23:30:00' AS created_at)")];
    $shifted = function (bool $keepTime) use ($resource, $adapter, $table): string {
        $expression = $resource->getStoreTZOffsetQuery($table, 'o.created_at', '2025-01-01 00:00:00', '2025-12-31 23:59:59', Mage_Core_Model_App::ADMIN_STORE_ID, $keepTime);
        return (string) $adapter->fetchOne($adapter->select()->from($table, ['local' => new Maho\Db\Expr($expression)]));
    };

    // British summer time is one hour ahead of UTC, so the order moves past midnight
    expect(substr($shifted(true), 0, 19))->toBe('2025-07-02 00:30:00')
        ->and(substr($shifted(false), 0, 10))->toBe('2025-07-02');
});

it('puts an order in the bucket of its hour on the 24 hour dashboard chart', function (): void {
    if (Mage::getStoreConfigFlag('sales/dashboard/use_aggregated_data')) {
        $this->markTestSkipped('The dashboard uses the aggregated data');
    }
    Tests\Helpers\ReportFixture::snapshot();
    try {
        Tests\Helpers\ReportFixture::placeOrder(Tests\Helpers\ReportFixture::createProduct('report-hourly'), 1);
        $hour = Mage::app()->getLocale()->utcToStore(Mage_Core_Model_App::ADMIN_STORE_ID)->format('Y-m-d H:00');

        $collection = Mage::getResourceModel('reports/order_collection')->prepareSummary('24h', 0, 0, 0);
        $quantities = [];
        foreach ($collection as $row) {
            $quantities[$row->getData('range')] = (int) $row->getData('quantity');
        }

        expect($quantities[$hour] ?? 0)->toBeGreaterThanOrEqual(1);
    } finally {
        Tests\Helpers\ReportFixture::restore();
    }
});
