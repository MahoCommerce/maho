<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Guards two cron aggregations that built invalid SQL: the AI usage roll-up expanded a
 * two-placeholder BETWEEN with one array bind, and the bestsellers rating position seeded
 * a '0000-00-00' sentinel that a strict server rejects inside an INSERT ... SELECT.
 */

const BESTSELLERS_TEST_PERIOD = '2035-01-01';

afterEach(function () {
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('sales/bestsellers_aggregated_daily'),
        ['period = ?' => BESTSELLERS_TEST_PERIOD],
    );
});

it('aggregates AI usage with a valid date range condition', function () {
    (new Maho_Ai_Model_TaskRunner())->aggregateUsage();
    expect(true)->toBeTrue();
});

it('ranks bestsellers without a zero-date sentinel', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter  = $resource->getConnection('core_write');

    if (!$adapter instanceof \Maho\Db\Adapter\Pdo\Mysql) {
        $this->markTestSkipped('The user-variable sentinel exists only in the MySQL helper.');
    }

    $productIds = $adapter->fetchCol(
        $adapter->select()->from($resource->getTableName('catalog/product'), ['entity_id'])->limit(2),
    );
    if (count($productIds) < 2) {
        $this->markTestSkipped('The catalog holds fewer than two products.');
    }

    $table = $resource->getTableName('sales/bestsellers_aggregated_daily');
    foreach ([[$productIds[0], 3], [$productIds[1], 9]] as [$productId, $qty]) {
        $adapter->insertOnDuplicate($table, [
            'period'          => BESTSELLERS_TEST_PERIOD,
            'store_id'        => 1,
            'product_id'      => $productId,
            'product_name'    => 'Aggregation test product',
            'product_price'   => 10.0,
            'product_type_id' => 'simple',
            'qty_ordered'     => $qty,
            'rating_pos'      => 1,
        ]);
    }

    $method = new ReflectionMethod(Mage_Sales_Model_Resource_Report_Bestsellers::class, '_updateRatingPos');
    $method->invoke(Mage::getResourceModel('sales/report_bestsellers'), 'daily');

    $warnings = $adapter->fetchAll('SHOW WARNINGS');
    $messages = array_column($warnings, 'Message');
    expect(implode("\n", $messages))->not->toContain('0000-00-00');

    $ranked = $adapter->fetchPairs(
        $adapter->select()
            ->from($table, ['product_id', 'rating_pos'])
            ->where('period = ?', BESTSELLERS_TEST_PERIOD)
            ->where('store_id = ?', 1),
    );
    expect((int) $ranked[$productIds[1]])->toBe(1);
    expect((int) $ranked[$productIds[0]])->toBe(2);
});
