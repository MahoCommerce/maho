<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->tableName = 'test_payment_store_filter_' . uniqid();
    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('restriction_id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('store_ids', Table::TYPE_TEXT, 255, ['nullable' => true]);
    $this->adapter->createTable($table);
    foreach ([
        1 => '1',
        2 => '1,12',
        3 => '11,12',
        4 => '',
        5 => null,
    ] as $id => $storeIds) {
        $this->adapter->insert($this->tableName, [
            'restriction_id' => $id,
            'store_ids' => $storeIds,
        ]);
    }
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

it('matches complete store IDs and keeps global scopes', function () {
    $collection = Mage::getResourceModel('payment/restriction_collection')
        ->setMainTable($this->tableName)
        ->addStoreFilter(1);

    $ids = array_map(intval(...), $collection->getAllIds());
    sort($ids);

    expect($ids)->toBe([1, 2, 4, 5]);
});
