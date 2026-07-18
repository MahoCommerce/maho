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

    // The connection may already have been opened by Mage::app() in setUp() before
    // developer mode was enabled, so force ONLY_FULL_GROUP_BY explicitly on genuine
    // MySQL (MariaDB's ONLY_FULL_GROUP_BY lacks functional-dependency detection and
    // would reject valid PK-grouped queries, matching the Catalog suite's approach).
    if ($adapter instanceof \Maho\Db\Adapter\Pdo\Mysql) {
        $version = (string) $adapter->fetchOne('SELECT VERSION()');
        if (stripos($version, 'mariadb') === false) {
            foreach (['core_read', 'core_write'] as $connectionName) {
                Mage::getSingleton('core/resource')->getConnection($connectionName)
                    ->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
            }
        }
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

// ---------------------------------------------------------------------------
// Shared helpers for the strict-GROUP-BY regression tests below
// ---------------------------------------------------------------------------

const REPORTS_SGB_SKU_PREFIX = 'reports-strict-groupby-';

afterAll(function (): void {
    reportsGbCleanupProducts();
});

/**
 * Create (once per file) two enabled simple products used as fixture targets
 * for the rating-position, order, cart, and tag tests. Returns their ids.
 *
 * @return list<int>
 */
function reportsGbProductIds(int $count): array
{
    static $productIds = null;

    if ($productIds === null) {
        $setId = (int) Mage::getModel('eav/entity_type')
            ->loadByCode(Mage_Catalog_Model_Product::ENTITY)
            ->getDefaultAttributeSetId();

        $productIds = [];
        foreach (['a', 'b'] as $suffix) {
            $sku = REPORTS_SGB_SKU_PREFIX . $suffix;
            $existingId = (int) Mage::getModel('catalog/product')->getIdBySku($sku);
            if ($existingId) {
                $productIds[] = $existingId;
                continue;
            }
            $product = Mage::getModel('catalog/product');
            $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                ->setAttributeSetId($setId)
                ->setSku($sku)
                ->setName('Reports strict GROUP BY fixture ' . $suffix)
                ->setDescription('Reports strict GROUP BY fixture')
                ->setShortDescription('Reports strict GROUP BY fixture')
                ->setPrice(10.00)
                ->setWeight(1)
                ->setTaxClassId(0)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setWebsiteIds([1])
                ->setStockData([
                    'use_config_manage_stock' => 1,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => 100,
                ])
                ->save();
            $productIds[] = (int) $product->getId();
        }
    }

    return array_slice($productIds, 0, $count);
}

/**
 * Delete the fixture products. Runs after the last tearDown() (which
 * Mage::reset()s the registry). Best effort — the runner rebuilds a fresh
 * test DB each run anyway.
 */
function reportsGbCleanupProducts(): void
{
    Mage::register('isSecureArea', true, true);
    try {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => REPORTS_SGB_SKU_PREFIX . '%']);
            foreach ($collection as $product) {
                $product->delete();
            }
        } catch (\Throwable $e) {
            // Best effort.
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
}

/**
 * First non-admin store id, or 0 when none exists.
 */
function reportsGbStoreId(): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('core/store'), ['store_id'])
            ->where('store_id > 0')
            ->order('store_id ASC')
            ->limit(1),
    );
}

/**
 * Delete every row with one of the given periods from the given tables.
 * The fixtures below use 1997 periods, which cannot collide with real data.
 */
function reportsGbCleanPeriods(array $tables, array $periods): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    foreach ($tables as $table) {
        foreach ($periods as $period) {
            $adapter->delete($table, ['period = ?' => $period]);
        }
    }
}

// ---------------------------------------------------------------------------
// Requirement 8: updateReportRatingPos (reports resource helper) — day, month
// and year aggregation of the most-viewed report under strict GROUP BY
// ---------------------------------------------------------------------------
it('computes day, month and year viewed-product rating positions via updateReportRatingPos', function () {
    $ids = reportsGbProductIds(2);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need at least two products and one store for rating position fixtures');
    }
    [$productA, $productB] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $daily = $resource->getTableName('reports/viewed_aggregated_daily');
    $monthly = $resource->getTableName('reports/viewed_aggregated_monthly');
    $yearly = $resource->getTableName('reports/viewed_aggregated_yearly');

    $dailyPeriods = ['1997-05-10', '1997-05-20'];
    reportsGbCleanPeriods([$daily], $dailyPeriods);
    reportsGbCleanPeriods([$monthly], ['1997-05-01']);
    reportsGbCleanPeriods([$yearly], ['1997-01-01']);

    // Product A: 5 views on May 10 + 3 views on May 20 (monthly total 8)
    // Product B: 4 views on May 10 (monthly total 4)
    $rows = [
        [$productA, '1997-05-10', 5],
        [$productB, '1997-05-10', 4],
        [$productA, '1997-05-20', 3],
    ];
    foreach ($rows as [$productId, $period, $views]) {
        $adapter->insert($daily, [
            'period' => $period,
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_name' => 'GB test product ' . $productId,
            'product_price' => 9.9900,
            'views_num' => $views,
            'rating_pos' => 0,
        ]);
    }

    try {
        // Exercise all three shapes of the fixed helper method (Mysql or Pgsql
        // resource helper depending on the connection). Any strict-GROUP-BY
        // violation throws here and fails the test.
        $helper = Mage::getResourceHelper('reports');
        $helper->updateReportRatingPos('day', 'views_num', $daily, $daily);
        $helper->updateReportRatingPos('month', 'views_num', $daily, $monthly);
        $helper->updateReportRatingPos('year', 'views_num', $daily, $yearly);

        // Day: positions are recomputed in place on the daily table
        $dayRows = $adapter->fetchAll(
            $adapter->select()
                ->from($daily, ['product_id', 'period', 'views_num', 'rating_pos'])
                ->where('period IN (?)', $dailyPeriods)
                ->where('store_id = ?', $storeId),
        );
        $dayPos = [];
        foreach ($dayRows as $row) {
            $dayPos[substr((string) $row['period'], 0, 10) . '/' . $row['product_id']] = (int) $row['rating_pos'];
        }
        expect($dayPos['1997-05-10/' . $productA])->toBe(1)
            ->and($dayPos['1997-05-10/' . $productB])->toBe(2)
            ->and($dayPos['1997-05-20/' . $productA])->toBe(1);

        // Month: views are summed per product and ranked within the month
        $monthRows = $adapter->fetchAll(
            $adapter->select()
                ->from($monthly, ['product_id', 'views_num', 'rating_pos'])
                ->where('period = ?', '1997-05-01')
                ->where('store_id = ?', $storeId),
        );
        $monthByProduct = [];
        foreach ($monthRows as $row) {
            $monthByProduct[(int) $row['product_id']] = $row;
        }
        expect($monthByProduct)->toHaveCount(2)
            ->and((int) $monthByProduct[$productA]['views_num'])->toBe(8)
            ->and((int) $monthByProduct[$productA]['rating_pos'])->toBe(1)
            ->and((int) $monthByProduct[$productB]['views_num'])->toBe(4)
            ->and((int) $monthByProduct[$productB]['rating_pos'])->toBe(2);

        // Year: same totals, ranked within the year
        $yearRows = $adapter->fetchAll(
            $adapter->select()
                ->from($yearly, ['product_id', 'views_num', 'rating_pos'])
                ->where('period = ?', '1997-01-01')
                ->where('store_id = ?', $storeId),
        );
        $yearByProduct = [];
        foreach ($yearRows as $row) {
            $yearByProduct[(int) $row['product_id']] = $row;
        }
        expect($yearByProduct)->toHaveCount(2)
            ->and((int) $yearByProduct[$productA]['views_num'])->toBe(8)
            ->and((int) $yearByProduct[$productA]['rating_pos'])->toBe(1)
            ->and((int) $yearByProduct[$productB]['rating_pos'])->toBe(2);
    } finally {
        reportsGbCleanPeriods([$daily], $dailyPeriods);
        reportsGbCleanPeriods([$monthly], ['1997-05-01']);
        reportsGbCleanPeriods([$yearly], ['1997-01-01']);
    }
});

// ---------------------------------------------------------------------------
// Requirement 9: updateReportRatingPos qty_ordered variant — composite product
// types must rank after simple ones (the ORDER BY tiebreaker moved into MAX())
// ---------------------------------------------------------------------------
it('ranks composite products after simple ones in the qty_ordered rating aggregation', function () {
    $ids = reportsGbProductIds(2);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need at least two products and one store for bestsellers fixtures');
    }
    [$productA, $productB] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $daily = $resource->getTableName('sales/bestsellers_aggregated_daily');
    $monthly = $resource->getTableName('sales/bestsellers_aggregated_monthly');

    $dailyPeriods = ['1997-06-10', '1997-06-20'];
    reportsGbCleanPeriods([$daily], $dailyPeriods);
    reportsGbCleanPeriods([$monthly], ['1997-06-01']);

    // Product A: simple, qty 3 + 7 = 10 for the month
    // Product B: configurable (composite), qty 20 — higher qty but must rank AFTER the simple
    $rows = [
        [$productA, '1997-06-10', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, 3],
        [$productA, '1997-06-20', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, 7],
        [$productB, '1997-06-10', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE, 20],
    ];
    foreach ($rows as [$productId, $period, $typeId, $qty]) {
        $adapter->insert($daily, [
            'period' => $period,
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_type_id' => $typeId,
            'product_name' => 'GB bestseller ' . $productId,
            'product_price' => 19.9900,
            'qty_ordered' => $qty,
            'rating_pos' => 0,
        ]);
    }

    try {
        Mage::getResourceHelper('reports')
            ->updateReportRatingPos('month', 'qty_ordered', $daily, $monthly);

        $monthRows = $adapter->fetchAll(
            $adapter->select()
                ->from($monthly, ['product_id', 'qty_ordered', 'rating_pos'])
                ->where('period = ?', '1997-06-01')
                ->where('store_id = ?', $storeId),
        );
        $byProduct = [];
        foreach ($monthRows as $row) {
            $byProduct[(int) $row['product_id']] = $row;
        }
        expect($byProduct)->toHaveCount(2)
            // monthly qty is summed across the daily rows: 3 + 7 = 10
            ->and((float) $byProduct[$productA]['qty_ordered'])->toBe(10.0)
            // the simple product ranks first even though the composite sold more
            ->and((int) $byProduct[$productA]['rating_pos'])->toBe(1)
            ->and((float) $byProduct[$productB]['qty_ordered'])->toBe(20.0)
            ->and((int) $byProduct[$productB]['rating_pos'])->toBe(2);
    } finally {
        reportsGbCleanPeriods([$daily], $dailyPeriods);
        reportsGbCleanPeriods([$monthly], ['1997-06-01']);
    }
});

// ---------------------------------------------------------------------------
// Requirement 10: Most Viewed report collection boundary select
// (_makeBoundarySelect wraps the period expression in MAX())
// ---------------------------------------------------------------------------
it('loads the Most Viewed monthly report with mid-month date boundaries and sums views per product', function () {
    $ids = reportsGbProductIds(1);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need one product and one store for the boundary select fixture');
    }
    [$productId] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $daily = $resource->getTableName('reports/viewed_aggregated_daily');

    $dailyPeriods = ['1997-05-10', '1997-05-15'];
    reportsGbCleanPeriods([$daily], $dailyPeriods);
    foreach ([['1997-05-10', 5], ['1997-05-15', 3]] as [$period, $views]) {
        $adapter->insert($daily, [
            'period' => $period,
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_name' => 'GB boundary product',
            'product_price' => 5.0000,
            'views_num' => $views,
            'rating_pos' => 1,
        ]);
    }

    try {
        // A same-month partial range forces _beforeLoad() to replace the main
        // select with a _makeBoundarySelect() union — the fixed GROUP BY shape.
        $collection = Mage::getResourceModel('reports/report_product_viewed_collection')
            ->setPeriod('month')
            ->setDateRange('1997-05-05', '1997-05-20')
            ->addStoreFilter([$storeId]);
        $collection->load();

        $matches = [];
        foreach ($collection as $item) {
            if ((int) $item->getProductId() === $productId) {
                $matches[] = $item;
            }
        }
        expect($matches)->toHaveCount(1)
            ->and((int) $matches[0]->getViewsNum())->toBe(8)
            ->and((string) $matches[0]->getPeriod())->toStartWith('1997-05');
    } finally {
        reportsGbCleanPeriods([$daily], $dailyPeriods);
    }
});

// ---------------------------------------------------------------------------
// Requirement 11: addOrderedQty loads and sums quantities per product
// ---------------------------------------------------------------------------
it('sums ordered quantities per product in addOrderedQty and loads without strict GROUP BY errors', function () {
    $ids = reportsGbProductIds(1);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need one product and one store for order fixtures');
    }
    [$productId] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $orderTable = $resource->getTableName('sales/order');
    $orderItemTable = $resource->getTableName('sales/order_item');

    $adapter->insert($orderTable, [
        'state' => Mage_Sales_Model_Order::STATE_NEW,
        'status' => 'pending',
        'store_id' => $storeId,
        'protect_code' => 'gbtest',
    ]);
    $orderId = (int) $adapter->lastInsertId($orderTable);

    foreach ([3, 4] as $qty) {
        $adapter->insert($orderItemTable, [
            'order_id' => $orderId,
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'name' => 'GB ordered item',
            'sku' => 'gb-ordered-item',
            'qty_ordered' => $qty,
        ]);
    }

    try {
        $collection = Mage::getResourceModel('reports/product_collection');
        $collection->addOrderedQty();
        $collection->load();

        $found = null;
        foreach ($collection as $item) {
            if ((int) $item->getId() === $productId) {
                $found = $item;
                break;
            }
        }
        expect($found)->not->toBeNull()
            // >= because other tests/fixtures may have ordered the same product
            ->and((float) $found->getOrderedQty())->toBeGreaterThanOrEqual(7.0)
            ->and($found->getOrderItemsName())->not->toBeEmpty();
    } finally {
        // Cascades to the order items
        $adapter->delete($orderTable, ['entity_id = ?' => $orderId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 12: addCartsCount counts carts, excludes cart-less products, and
// getSize() matches the number of loaded rows (WHERE EXISTS survives the
// GROUP/HAVING reset in getSelectCountSql)
// ---------------------------------------------------------------------------
it('counts products in carts via addCartsCount with getSize matching the loaded rows', function () {
    $ids = reportsGbProductIds(1);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need one product and one store for quote fixtures');
    }
    [$productId] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $quoteTable = $resource->getTableName('sales/quote');
    $quoteItemTable = $resource->getTableName('sales/quote_item');

    $adapter->insert($quoteTable, [
        'store_id' => $storeId,
        'is_active' => 1,
    ]);
    $quoteId = (int) $adapter->lastInsertId($quoteTable);
    $adapter->insert($quoteItemTable, [
        'quote_id' => $quoteId,
        'store_id' => $storeId,
        'product_id' => $productId,
        'sku' => 'gb-cart-item',
        'name' => 'GB cart item',
        'qty' => 1,
    ]);

    try {
        $collection = Mage::getResourceModel('reports/product_collection');
        $collection->addCartsCount();
        $size = $collection->getSize();
        $collection->load();

        $found = null;
        foreach ($collection as $item) {
            if ((int) $item->getId() === $productId) {
                $found = $item;
                break;
            }
        }
        expect($found)->not->toBeNull()
            ->and((int) $found->getCarts())->toBeGreaterThanOrEqual(1)
            // WHERE EXISTS keeps count and load consistent
            ->and($size)->toBe(count($collection->getItems()));

        // A product that is in no active cart must be excluded by the WHERE EXISTS filter
        $noCartProductId = (int) $adapter->fetchOne(
            $adapter->select()
                ->from(['e' => $resource->getTableName('catalog/product')], ['entity_id'])
                ->where(sprintf(
                    'NOT EXISTS (SELECT 1 FROM %s AS qi WHERE qi.product_id = e.entity_id)',
                    $quoteItemTable,
                ))
                ->order('entity_id ASC')
                ->limit(1),
        );
        if ($noCartProductId) {
            expect($collection->getItemById($noCartProductId))->toBeNull();
        }
    } finally {
        // Cascades to the quote items
        $adapter->delete($quoteTable, ['entity_id = ?' => $quoteId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 13: Reports Tag Product collection — MIN()-wrapped tag columns in
// _joinFields keep both grouping paths valid and preserve tag data
// ---------------------------------------------------------------------------
it('loads the Reports Tag Product collection grouped by tag and by product with correct tag data', function () {
    $ids = reportsGbProductIds(1);
    $storeId = reportsGbStoreId();
    if (!$ids || !$storeId) {
        $this->markTestSkipped('Need one product and one store for tag fixtures');
    }
    [$productId] = $ids;

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $tagTable = $resource->getTableName('tag/tag');
    $relationTable = $resource->getTableName('tag/relation');

    $tagName = 'gb-reports-tag-' . uniqid();
    $adapter->insert($tagTable, [
        'name' => $tagName,
        'status' => Mage_Tag_Model_Tag::STATUS_APPROVED,
        'first_store_id' => $storeId,
    ]);
    $tagId = (int) $adapter->lastInsertId($tagTable);
    $adapter->insert($relationTable, [
        'tag_id' => $tagId,
        'product_id' => $productId,
        'store_id' => $storeId,
        'active' => 1,
    ]);

    try {
        // Grouped by tag: tag columns are representative per group and
        // ORDER BY tag_name must stay valid as an aggregate alias
        $byTag = Mage::getResourceModel('reports/tag_product_collection');
        $byTag->addGroupByTag()->setOrder('tag_name', 'ASC');
        $byTag->load();

        $found = null;
        foreach ($byTag as $item) {
            if ((int) $item->getTagId() === $tagId) {
                $found = $item;
                break;
            }
        }
        expect($found)->not->toBeNull()
            ->and($found->getTagName())->toBe($tagName)
            ->and((int) $found->getStatus())->toBe(Mage_Tag_Model_Tag::STATUS_APPROVED);

        // Grouped by product: the other grouping path must load too
        $byProduct = Mage::getResourceModel('reports/tag_product_collection');
        $byProduct->addGroupByProduct()->addTagedCount();
        $byProduct->load();
        expect($byProduct->getItemById($productId))->not->toBeNull();
    } finally {
        $adapter->delete($relationTable, ['tag_id = ?' => $tagId]);
        $adapter->delete($tagTable, ['tag_id = ?' => $tagId]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 14: Downloads collection addSummary with link_title ordering
// (load() executes the grouped SELECT that getSize() cannot exercise)
// ---------------------------------------------------------------------------
it('loads the Downloads collection with addSummary and link_title ordering without strict GROUP BY errors', function () {
    $collection = Mage::getResourceModel('reports/product_downloads_collection');
    $collection->addSummary()->setOrder('link_title', 'ASC');
    $collection->load();
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 15: product index collection combined with addPriceData — the
// derived idx_table join must leave price_index columns legal (the shape used
// by Mage_Reports_Block_Product_Abstract::getItemsCollection)
// ---------------------------------------------------------------------------
it('loads the Recently Viewed collection with price data joined without strict GROUP BY errors', function () {
    $storeId = reportsGbStoreId();
    if (!$storeId) {
        $this->markTestSkipped('Need a non-admin store for the price data join');
    }
    $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();

    $collection = Mage::getModel('reports/product_index_viewed')->getCollection();
    $collection->addAttributeToSelect('name');
    $collection->addPriceData(0, $websiteId);
    $collection->addIndexFilter();
    $collection->setAddedAtOrder();
    $collection->load();
    expect($collection->isLoaded())->toBeTrue();
});
