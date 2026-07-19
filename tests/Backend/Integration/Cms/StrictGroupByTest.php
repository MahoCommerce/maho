<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Skip the entire suite on SQLite — SQLite does not enforce GROUP BY strictness.
 * All four requirements are meaningful only on MySQL (ONLY_FULL_GROUP_BY) and PostgreSQL.
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite does not enforce strict GROUP BY; test is MySQL/PostgreSQL only.');
    }

    Mage::setIsDeveloperMode(true);
});

// ---------------------------------------------------------------------------
// Requirement 1: CMS Page collection loads with store filter, no GROUP BY error
// ---------------------------------------------------------------------------
it('loads the CMS Page collection with a store filter applied without strict GROUP BY errors', function () {
    /** @var Mage_Cms_Model_Resource_Page_Collection $collection */
    $collection = Mage::getModel('cms/page')->getCollection();
    $collection->addStoreFilter(0); // store 0 = admin store, always exists

    // This must not throw a DB exception about ONLY_FULL_GROUP_BY or equivalent
    expect(fn() => $collection->load())->not->toThrow(\Exception::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 2: CMS Block collection loads with store filter, no GROUP BY error
// ---------------------------------------------------------------------------
it('loads the CMS Block collection with a store filter applied without strict GROUP BY errors', function () {
    /** @var Mage_Cms_Model_Resource_Block_Collection $collection */
    $collection = Mage::getModel('cms/block')->getCollection();
    $collection->addStoreFilter(0); // store 0 = admin store, always exists

    expect(fn() => $collection->load())->not->toThrow(\Exception::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 3: One row per page when the same page is assigned to multiple stores
// ---------------------------------------------------------------------------
it('returns one row per page when the same page is assigned to multiple stores', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $resource = Mage::getSingleton('core/resource');
    $pageTable = $resource->getTableName('cms/page');
    $pageStoreTable = $resource->getTableName('cms/page_store');

    // Insert a test page
    $adapter->insert($pageTable, [
        'title'       => 'StrictGroupBy Test Page',
        'identifier'  => 'strict-group-by-test-page-' . uniqid(),
        'is_active'   => 1,
        'sort_order'  => 0,
    ]);
    $pageId = (int) $adapter->lastInsertId($pageTable);

    // Assign to two different stores: admin store (0) and first non-admin store (1)
    // Store 1 always exists after installation (sample data creates it).
    $storeId1 = 0;
    $storeId2 = 1;

    $adapter->insert($pageStoreTable, ['page_id' => $pageId, 'store_id' => $storeId1]);
    $adapter->insert($pageStoreTable, ['page_id' => $pageId, 'store_id' => $storeId2]);

    try {
        /** @var Mage_Cms_Model_Resource_Page_Collection $collection */
        $collection = Mage::getModel('cms/page')->getCollection();
        // Filter by both stores so the join produces two rows before deduplication
        $collection->addStoreFilter($storeId2, withAdmin: true);
        $collection->addFieldToFilter('main_table.page_id', $pageId);
        $collection->load();

        $items = $collection->getItems();
        expect(count($items))->toBe(1);
    } finally {
        // Clean up test data
        $adapter->delete($pageStoreTable, ['page_id = ?' => $pageId]);
        $adapter->delete($pageTable, ['page_id = ?' => $pageId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: One row per block when the same block is assigned to multiple stores
// ---------------------------------------------------------------------------
it('returns one row per block when the same block is assigned to multiple stores', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $resource = Mage::getSingleton('core/resource');
    $blockTable = $resource->getTableName('cms/block');
    $blockStoreTable = $resource->getTableName('cms/block_store');

    // Insert a test block
    $adapter->insert($blockTable, [
        'title'      => 'StrictGroupBy Test Block',
        'identifier' => 'strict-group-by-test-block-' . uniqid(),
        'is_active'  => 1,
        'content'    => 'Test content',
    ]);
    $blockId = (int) $adapter->lastInsertId($blockTable);

    $storeId1 = 0;
    $storeId2 = 1;

    $adapter->insert($blockStoreTable, ['block_id' => $blockId, 'store_id' => $storeId1]);
    $adapter->insert($blockStoreTable, ['block_id' => $blockId, 'store_id' => $storeId2]);

    try {
        /** @var Mage_Cms_Model_Resource_Block_Collection $collection */
        $collection = Mage::getModel('cms/block')->getCollection();
        $collection->addStoreFilter($storeId2, withAdmin: true);
        $collection->addFieldToFilter('main_table.block_id', $blockId);
        $collection->load();

        $items = $collection->getItems();
        expect(count($items))->toBe(1);
    } finally {
        // Clean up test data
        $adapter->delete($blockStoreTable, ['block_id = ?' => $blockId]);
        $adapter->delete($blockTable, ['block_id = ?' => $blockId]);
    }
});
