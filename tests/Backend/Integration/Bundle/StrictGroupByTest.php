<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fix in
 * Mage_Bundle_Block_Catalog_Product_List_Partof::_prepareData().
 *
 * The old implementation joined bundle/option + bundle/selection onto the
 * product collection and deduped the multiplied rows with GROUP BY entity_id,
 * which ONLY_FULL_GROUP_BY (MySQL) and PostgreSQL reject because the select
 * list contains ungrouped columns. The fix removed both joins and the GROUP BY
 * in favor of a WHERE e.entity_id IN (subquery) prefilter, so:
 *  - the assembled SQL must contain no GROUP BY at all;
 *  - the query must execute under strict mode;
 *  - each parent bundle must be returned exactly once even when the current
 *    product sits in multiple options of the same bundle (the dedup job the
 *    GROUP BY used to do).
 *
 * The dedup/behavior tests run on every engine (SQLite included); only the
 * strict-mode execution test is skipped on SQLite, which has no strict mode.
 */

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Build the "part of bundles" collection exactly as the block does:
 * register the given product id, create the real block, and invoke the
 * fixed _prepareData() method (protected → reflection).
 */
function bundlePartofBuildCollection(int $productId): Mage_Catalog_Model_Resource_Product_Collection
{
    if (Mage::registry('product') !== null) {
        Mage::unregister('product');
    }
    Mage::register('product', Mage::getModel('catalog/product')->setId($productId));

    try {
        /** @var Mage_Bundle_Block_Catalog_Product_List_Partof $block */
        $block = Mage::app()->getLayout()->createBlock('bundle/catalog_product_list_partof');
        (new ReflectionMethod($block, '_prepareData'))->invoke($block);
        return $block->getItemCollection();
    } finally {
        Mage::unregister('product');
    }
}

/**
 * Find a product that survives every filter the block applies (store, status,
 * visibility, price index join), so it is guaranteed to show up as a "parent
 * bundle" in the block's collection once bundle option/selection rows point
 * at it. Returns null when the sample catalog has no such product.
 */
function bundlePartofFindListedProductId(): ?int
{
    $probe = Mage::getModel('catalog/product')->getResourceCollection()
        ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
        ->addStoreFilter()
        ->addAttributeToFilter('status', [
            'in' => Mage::getSingleton('catalog/product_status')->getSaleableStatusIds(),
        ])
        ->addPriceData()
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds());
    $probe->setPageSize(1)->load();

    foreach ($probe as $item) {
        return (int) $item->getId();
    }
    return null;
}

/**
 * Insert two bundle options on the same parent product, each containing the
 * same child product as a selection. This is exactly the multi-valued join
 * scenario the removed GROUP BY existed to dedupe: the child is part of the
 * parent bundle twice, via two different options.
 * Returns the inserted option ids (selections cascade-delete with them).
 */
function bundlePartofInsertFixture(int $parentId, int $childId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $optionTable = $resource->getTableName('bundle/option');
    $selectionTable = $resource->getTableName('bundle/selection');

    $optionIds = [];
    foreach ([1, 2] as $position) {
        $adapter->insert($optionTable, [
            'parent_id' => $parentId,
            'required' => 0,
            'position' => $position,
            'type' => 'select',
        ]);
        $optionId = (int) $adapter->lastInsertId($optionTable);
        $optionIds[] = $optionId;

        $adapter->insert($selectionTable, [
            'option_id' => $optionId,
            'parent_product_id' => $parentId,
            'product_id' => $childId,
            'position' => $position,
            'is_default' => 1,
            'selection_price_type' => 0,
            'selection_price_value' => 0,
            'selection_qty' => 1,
            'selection_can_change_qty' => 0,
        ]);
    }

    return $optionIds;
}

/**
 * Remove the bundle fixture rows (selections first, then options).
 */
function bundlePartofCleanFixture(array $optionIds): void
{
    if (!$optionIds) {
        return;
    }
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->delete(
        $resource->getTableName('bundle/selection'),
        ['option_id IN (?)' => $optionIds],
    );
    $adapter->delete(
        $resource->getTableName('bundle/option'),
        ['option_id IN (?)' => $optionIds],
    );
}

// ---------------------------------------------------------------------------
// Requirement 1: the fixed query shape — IN-subquery prefilter, no GROUP BY
// ---------------------------------------------------------------------------

it('builds the part-of-bundles collection with an IN-subquery prefilter and no GROUP BY', function () {
    $resource = Mage::getSingleton('core/resource');
    $selectionTable = $resource->getTableName('bundle/selection');
    $optionTable = $resource->getTableName('bundle/option');

    // Any product id works: the SQL shape is independent of the data.
    $collection = bundlePartofBuildCollection(99999999);
    $sql = $collection->getSelect()->assemble();

    // The dedup-only GROUP BY must be gone entirely.
    expect(stripos($sql, 'GROUP BY'))->toBeFalse();

    // The bundle tables must appear only inside the IN (...) subquery, not as joins
    // multiplying the result rows.
    expect($sql)->toContain($optionTable)
        ->and($sql)->toContain($selectionTable)
        ->and(stripos($sql, 'IN ('))->not->toBeFalse();
});

// ---------------------------------------------------------------------------
// Requirement 2: strict-mode execution (MySQL ONLY_FULL_GROUP_BY / PostgreSQL)
// ---------------------------------------------------------------------------

it('executes the part-of-bundles query without strict GROUP BY errors', function () {
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($liveAdapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode; test is MySQL/PostgreSQL only.');
    }

    $parentId = bundlePartofFindListedProductId();
    if ($parentId === null) {
        $this->markTestSkipped('No listable product available to seed bundle fixtures.');
    }

    $resource = Mage::getSingleton('core/resource');
    $childId = (int) $resource->getConnection('core_read')->fetchOne(
        $resource->getConnection('core_read')->select()
            ->from($resource->getTableName('catalog/product'), ['entity_id'])
            ->where('entity_id != ?', $parentId)
            ->limit(1),
    );
    if (!$childId) {
        $this->markTestSkipped('Catalog needs at least two products for bundle fixtures.');
    }

    $optionIds = bundlePartofInsertFixture($parentId, $childId);
    try {
        $collection = bundlePartofBuildCollection($childId);
        $sql = $collection->getSelect()->assemble();

        // Mage::app() opened the live connection before developer mode could be
        // set, so (on MySQL) execute the assembled SQL through a fresh adapter
        // opened with ONLY_FULL_GROUP_BY active. PostgreSQL always enforces
        // strict GROUP BY, so the live adapter is sufficient there.
        if ($liveAdapter instanceof Mysql) {
            Mage::setIsDeveloperMode(true);
            $strictAdapter = new Mysql($liveAdapter->getConfig());
        } else {
            $strictAdapter = $liveAdapter;
        }

        // Throws on a GROUP BY violation (1055 on MySQL, 42803 on PostgreSQL),
        // which fails the test automatically.
        $rows = $strictAdapter->fetchAll($sql);

        // The fixture guarantees at least the parent bundle row.
        $parentRows = array_filter($rows, fn(array $row) => (int) $row['entity_id'] === $parentId);
        expect(count($parentRows))->toBe(1);
    } finally {
        bundlePartofCleanFixture($optionIds);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: dedup behavior — parent bundle returned exactly once
// ---------------------------------------------------------------------------

it('returns each parent bundle exactly once when the product sits in multiple options of the same bundle', function () {
    $parentId = bundlePartofFindListedProductId();
    if ($parentId === null) {
        $this->markTestSkipped('No listable product available to seed bundle fixtures.');
    }

    $resource = Mage::getSingleton('core/resource');
    $childId = (int) $resource->getConnection('core_read')->fetchOne(
        $resource->getConnection('core_read')->select()
            ->from($resource->getTableName('catalog/product'), ['entity_id'])
            ->where('entity_id != ?', $parentId)
            ->limit(1),
    );
    if (!$childId) {
        $this->markTestSkipped('Catalog needs at least two products for bundle fixtures.');
    }

    $optionIds = bundlePartofInsertFixture($parentId, $childId);
    try {
        // load() inside _prepareData() throws "item with the same id already
        // exists" if the query returns duplicate entity_id rows, so a
        // successful build already proves row-level dedup.
        $collection = bundlePartofBuildCollection($childId);

        $occurrences = 0;
        foreach ($collection as $item) {
            if ((int) $item->getId() === $parentId) {
                $occurrences++;
            }
        }
        expect($occurrences)->toBe(1);

        // getSize() (COUNT query) must agree with the loaded items: the old
        // GROUP BY made the two diverge on some engines.
        expect($collection->getSize())->toBe(count($collection->getItems()));
    } finally {
        bundlePartofCleanFixture($optionIds);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: prefilter behavior — product in no bundle yields no items
// ---------------------------------------------------------------------------

it('returns an empty collection for a product that is not part of any bundle', function () {
    // A nonexistent product id can be part of no bundle: the IN-subquery is empty.
    $collection = bundlePartofBuildCollection(99999999);

    expect(count($collection->getItems()))->toBe(0)
        ->and($collection->getSize())->toBe(0);
});
