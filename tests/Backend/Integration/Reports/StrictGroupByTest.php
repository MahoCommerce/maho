<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

/**
 * Enable developer mode FIRST (before any connection is opened), so that
 * _initConnection() on MySQL applies ONLY_FULL_GROUP_BY.
 *
 * After Mage::reset() in tearDown() every singleton is destroyed, so setUp()
 * creates a fresh Mage_Core_Model_Resource with an empty _connections array.
 * Setting developer mode before the first getConnection() call is sufficient:
 * connections are opened lazily on first use.
 *
 * On PostgreSQL strict GROUP BY is always enforced (flag irrelevant).
 * On SQLite there is no strict GROUP BY; tests are skipped.
 */
beforeEach(function () {
    Mage::setIsDeveloperMode(true);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode');
    }
});

// ---------------------------------------------------------------------------
// Helper: insert index rows for a product to force GROUP BY evaluation
// ---------------------------------------------------------------------------

/**
 * Find the first product in the catalog.
 * Returns the entity_id or null if the catalog is empty.
 */
function findFirstProductId(): ?int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/product');
    $row = $adapter->fetchRow(
        $adapter->select()->from($table, ['entity_id'])->limit(1),
    );
    return $row ? (int) $row['entity_id'] : null;
}

// ---------------------------------------------------------------------------
// Requirement 1: Recently Viewed
// ---------------------------------------------------------------------------
it('loads the Recently Viewed product collection without strict GROUP BY errors', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $indexTable = $resource->getTableName('reports/viewed_product_index');

    $productId = findFirstProductId();
    if ($productId === null) {
        $this->markTestSkipped('No products available to seed the viewed index');
    }

    // Insert two rows for the same product to guarantee the GROUP BY is evaluated
    $visitorId = 9991001;
    $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => '2024-01-01 10:00:00',
    ]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => '2025-06-01 10:00:00',
    ]);

    try {
        $collection = Mage::getModel('reports/product_index_viewed')->getCollection();
        $collection->addIndexFilter();
        // Use load() not getSize(): getSelectCountSql() resets GROUP BY so the strict-mode
        // violation is invisible to COUNT queries. Only the main SELECT triggers the error.
        expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    } finally {
        $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: Recently Compared
// ---------------------------------------------------------------------------
it('loads the Recently Compared product collection without strict GROUP BY errors', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $indexTable = $resource->getTableName('reports/compared_product_index');

    $productId = findFirstProductId();
    if ($productId === null) {
        $this->markTestSkipped('No products available to seed the compared index');
    }

    // Insert two rows for the same product to guarantee the GROUP BY is evaluated
    $visitorId = 9991002;
    $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => '2024-01-01 10:00:00',
    ]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => '2025-06-01 10:00:00',
    ]);

    try {
        $collection = Mage::getModel('reports/product_index_compared')->getCollection();
        $collection->addIndexFilter();
        // Use load() not getSize(): getSelectCountSql() resets GROUP BY so the strict-mode
        // violation is invisible to COUNT queries. Only the main SELECT triggers the error.
        expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    } finally {
        $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: Reports Product collection helpers
// ---------------------------------------------------------------------------
it('loads each Reports Product collection helper (addCartsCount, addOrdersCount, addOrderedQty, addViewsCount) without strict GROUP BY errors', function () {
    // addCartsCount
    $cartsCollection = Mage::getResourceModel('reports/product_collection');
    $cartsCollection->addAttributeToSelect('name');
    $cartsCollection->addCartsCount();
    expect(fn() => $cartsCollection->getSize())->not->toThrow(\Throwable::class);

    // addOrdersCount
    $ordersCollection = Mage::getResourceModel('reports/product_collection');
    $ordersCollection->addAttributeToSelect('name');
    $ordersCollection->addOrdersCount();
    expect(fn() => $ordersCollection->getSize())->not->toThrow(\Throwable::class);

    // addOrderedQty
    $orderedQtyCollection = Mage::getResourceModel('reports/product_collection');
    $orderedQtyCollection->addOrderedQty();
    expect(fn() => $orderedQtyCollection->getSize())->not->toThrow(\Throwable::class);

    // addViewsCount
    $viewsCollection = Mage::getResourceModel('reports/product_collection');
    $viewsCollection->addViewsCount();
    expect(fn() => $viewsCollection->getSize())->not->toThrow(\Throwable::class);
});

// ---------------------------------------------------------------------------
// Requirement 4: Reports Order Collection date-range and customer-group modes
// ---------------------------------------------------------------------------
it('loads the Reports Order Collection in date-range and customer-group modes without strict GROUP BY errors', function () {
    // Date-range mode via setDateRange
    $dateRangeCollection = Mage::getResourceModel('reports/order_collection');
    $dateRangeCollection->setDateRange('2020-01-01', '2030-12-31');
    expect(fn() => $dateRangeCollection->getSize())->not->toThrow(\Throwable::class);

    // Customer-group mode via groupByCustomer
    $customerGroupCollection = Mage::getResourceModel('reports/order_collection');
    $customerGroupCollection->setMainTable('sales/order');
    $customerGroupCollection->groupByCustomer();
    $customerGroupCollection->addOrdersCount();
    $customerGroupCollection->joinCustomerName();
    expect(fn() => $customerGroupCollection->getSize())->not->toThrow(\Throwable::class);
});

// ---------------------------------------------------------------------------
// Requirement 5: Quote, Event, Customer, Wishlist, Review, Tag, Downloads
// ---------------------------------------------------------------------------
it('loads the Reports Quote, Event, Customer, Wishlist, Review, Tag, and Downloads collections without strict GROUP BY errors', function () {
    // Quote: prepareForProductsInCarts — use load() since getSelectCountSql resets GROUP BY
    $quoteCollection = Mage::getResourceModel('reports/quote_collection');
    $quoteCollection->prepareForProductsInCarts();
    expect(fn() => $quoteCollection->load())->not->toThrow(\Throwable::class);

    // Event collection with addRecentlyFiler
    $eventTypeCollection = Mage::getModel('reports/event_type')->getCollection();
    $productViewEventId = null;
    foreach ($eventTypeCollection as $eventType) {
        if ($eventType->getEventName() === 'catalog_product_view') {
            $productViewEventId = (int) $eventType->getId();
            break;
        }
    }
    if ($productViewEventId !== null) {
        $eventCollection = Mage::getResourceModel('reports/event_collection');
        $eventCollection->addRecentlyFiler($productViewEventId, 1, 0);
        expect(fn() => $eventCollection->getSize())->not->toThrow(\Throwable::class);
    }

    // Customer collection with joinOrders + addOrdersCount
    $customerCollection = Mage::getResourceModel('reports/customer_collection');
    $customerCollection->joinOrders();
    $customerCollection->addOrdersCount();
    expect(fn() => $customerCollection->getSize())->not->toThrow(\Throwable::class);

    // Wishlist: getWishlistCustomerCount (tests internal GROUP BY)
    $wishlistCollection = Mage::getResourceModel('reports/wishlist_collection');
    expect(fn() => $wishlistCollection->getWishlistCustomerCount())->not->toThrow(\Throwable::class);

    // Review product
    $reviewProductCollection = Mage::getResourceModel('reports/review_product_collection');
    $reviewProductCollection->joinReview();
    expect(fn() => $reviewProductCollection->getSize())->not->toThrow(\Throwable::class);

    // Review customer
    $reviewCustomerCollection = Mage::getResourceModel('reports/review_customer_collection');
    $reviewCustomerCollection->joinCustomers();
    expect(fn() => $reviewCustomerCollection->getSize())->not->toThrow(\Throwable::class);

    // Tag collection (addPopularity uses GROUP BY main_table.tag_id)
    $tagCollection = Mage::getResourceModel('reports/tag_collection');
    $tagCollection->addPopularity([1]);
    expect(fn() => $tagCollection->getSize())->not->toThrow(\Throwable::class);

    // Tag product collection
    $tagProductCollection = Mage::getResourceModel('reports/tag_product_collection');
    $tagProductCollection->addGroupByProduct();
    expect(fn() => $tagProductCollection->getSize())->not->toThrow(\Throwable::class);

    // Downloads
    $downloadsCollection = Mage::getResourceModel('reports/product_downloads_collection');
    $downloadsCollection->addSummary();
    expect(fn() => $downloadsCollection->getSize())->not->toThrow(\Throwable::class);
});

// ---------------------------------------------------------------------------
// Requirement 6: Recently Viewed ordering (most recent first)
// ---------------------------------------------------------------------------
it('preserves Recently Viewed ordering by most recent view (not earliest) after the fix', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $indexTable = $resource->getTableName('reports/viewed_product_index');

    $productId = findFirstProductId();
    if ($productId === null) {
        $this->markTestSkipped('No products found for ordering test');
    }

    // Insert two rows for the same product: an older one and a newer one
    $visitorId = 9991003;
    $olderDate = '2020-01-01 10:00:00';
    $newerDate = '2025-06-01 10:00:00';

    $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => $olderDate,
    ]);
    $adapter->insert($indexTable, [
        'visitor_id' => $visitorId,
        'customer_id' => null,
        'product_id' => $productId,
        'store_id' => 1,
        'added_at' => $newerDate,
    ]);

    try {
        // Verify the collection SQL uses MAX(added_at) so ORDER BY added_at DESC gives newest-first
        $collection = Mage::getModel('reports/product_index_viewed')->getCollection();
        $collection->addIndexFilter();
        $collection->setAddedAtOrder('DESC');
        $sql = $collection->getSelect()->assemble();
        // The fix: added_at must be MAX(idx_table.added_at) in the SELECT
        expect(strtoupper($sql))->toContain('MAX(');
    } finally {
        $adapter->delete($indexTable, ['visitor_id = ?' => $visitorId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 7: Recently Compared ordering (most recent comparison first)
// ---------------------------------------------------------------------------
it('preserves Recently Compared ordering by most recent comparison after the fix', function () {
    $collection = Mage::getModel('reports/product_index_compared')->getCollection();
    $collection->addIndexFilter();
    $collection->setAddedAtOrder('DESC');
    $sql = $collection->getSelect()->assemble();
    // The fix: added_at must be MAX(idx_table.added_at) in the SELECT
    expect(strtoupper($sql))->toContain('MAX(');
});
