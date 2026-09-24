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
