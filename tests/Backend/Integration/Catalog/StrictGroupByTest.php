<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fixes in Mage_Catalog:
 *
 * - Mage_Catalog_Model_Resource_Attribute::isUsedBySuperProducts() no longer
 *   pulls the implicit entity.* wildcard into its grouped COUNT select.
 * - Mage_Catalog_Model_Resource_Product_Collection::getMaxAttributeValue(),
 *   getAttributeValueCountByRange() and getAttributeValueCount() reset
 *   COLUMNS/ORDER/GROUP/LIMIT on their cloned select before joining, drop the
 *   bogus GROUP BY e.entity_type_id (MAX is now an ungrouped aggregate) and
 *   group by the underlying expression instead of a SELECT alias.
 *
 * Strict-mode enforcement: MahoBackendTestCase::setUp() calls Mage::app(),
 * which opens the DB connection BEFORE beforeEach() can set developer mode, so
 * on genuine MySQL we force ONLY_FULL_GROUP_BY onto the live session
 * explicitly. MariaDB is deliberately exempt (its ONLY_FULL_GROUP_BY lacks
 * functional-dependency detection, see Maho\Db\Adapter\Pdo\Mysql). PostgreSQL
 * enforces strict GROUP BY natively. SQLite has no strict mode, but the
 * behavioral assertions (counts, dedup, return contracts) still run there.
 *
 * Pest v4 note: expect(fn)->not->toThrow() does NOT fail when an exception is
 * thrown, so the tests execute the fixed methods directly — a strict-mode
 * violation throws and fails the test automatically.
 */
beforeEach(function () {
    Mage::setIsDeveloperMode(true);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof Mysql) {
        $version = (string) $adapter->fetchOne('SELECT VERSION()');
        if (stripos($version, 'mariadb') === false) {
            $adapter->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
        }
    }
});

afterAll(function (): void {
    catalogStrictGroupByCleanup();
});

const CATALOG_SGB_SKU_PREFIX = 'strict-groupby-';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/**
 * Create (once per file) three enabled simple products with known prices:
 * two sharing 15.00 and one at 35.00, so MAX / per-value counts / range
 * buckets all have deterministic expected results.
 *
 * @return array{product_ids: list<int>, attribute_set_id: int}
 */
function catalogStrictGroupByFixture(): array
{
    static $fixture = null;
    if ($fixture !== null) {
        return $fixture;
    }

    $setId = (int) Mage::getModel('eav/entity_type')
        ->loadByCode(Mage_Catalog_Model_Product::ENTITY)
        ->getDefaultAttributeSetId();

    $productIds = [];
    foreach (['low-a' => 15.00, 'low-b' => 15.00, 'high' => 35.00] as $suffix => $price) {
        $product = Mage::getModel('catalog/product');
        $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId($setId)
            ->setSku(CATALOG_SGB_SKU_PREFIX . $suffix)
            ->setName('Strict GROUP BY fixture ' . $suffix)
            ->setDescription('Strict GROUP BY fixture')
            ->setShortDescription('Strict GROUP BY fixture')
            ->setPrice($price)
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

    $fixture = [
        'product_ids' => $productIds,
        'attribute_set_id' => $setId,
    ];
    return $fixture;
}

/**
 * Build a product collection restricted to the fixture SKUs, with an ORDER BY
 * and a LIMIT already stacked directly on the select — like layered navigation
 * and grid callers do. The fixed aggregate methods must reset these on their
 * internal clone, otherwise the aggregates come back truncated (and e.* plus
 * the stale ORDER BY violate strict GROUP BY).
 */
function catalogStrictGroupByCollection(): Mage_Catalog_Model_Resource_Product_Collection
{
    /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
    $collection = Mage::getResourceModel('catalog/product_collection');
    $collection->addAttributeToFilter('sku', ['like' => CATALOG_SGB_SKU_PREFIX . '%']);
    $collection->getSelect()->order('e.sku')->limit(1, 1);
    return $collection;
}

/**
 * Delete the fixture products. Runs after the last tearDown() (which
 * Mage::reset()s the registry), so isSecureArea is registered gracefully and
 * ALWAYS unregistered in finally. Best effort — the runner rebuilds a fresh
 * test DB each run anyway.
 */
function catalogStrictGroupByCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    try {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => CATALOG_SGB_SKU_PREFIX . '%']);
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

// ---------------------------------------------------------------------------
// Mage_Catalog_Model_Resource_Attribute::isUsedBySuperProducts()
// ---------------------------------------------------------------------------

it('counts super-product usage in isUsedBySuperProducts and honors the attribute-set filter', function () {
    $fixture = catalogStrictGroupByFixture();

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $superTable = $resource->getTableName('catalog/product_super_attribute');

    // Any real catalog_product attribute id satisfies the FK; "status" always exists.
    $attribute = Mage::getSingleton('eav/config')
        ->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'status');
    $attributeId = (int) $attribute->getAttributeId();

    // Two usages across two products: the grouped COUNT must aggregate them
    // into a single row instead of failing on the (removed) entity.* wildcard.
    $productIds = array_slice($fixture['product_ids'], 0, 2);
    foreach ($productIds as $position => $productId) {
        $adapter->insert($superTable, [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'position' => $position,
        ]);
    }

    try {
        $attributeResource = $attribute->getResource();

        expect((int) $attributeResource->isUsedBySuperProducts($attribute))->toBe(2);

        // Optional attribute_set_id WHERE branch: a matching set still counts both...
        expect((int) $attributeResource->isUsedBySuperProducts($attribute, $fixture['attribute_set_id']))->toBe(2);

        // ...and a non-matching set yields no row -> falsy, the boolean contract
        // deleteEntity() and _beforeDelete() rely on.
        expect($attributeResource->isUsedBySuperProducts($attribute, 999999))->toBeFalsy();
    } finally {
        $adapter->delete($superTable, ['attribute_id = ?' => $attributeId]);
    }
});

// ---------------------------------------------------------------------------
// Mage_Catalog_Model_Resource_Product_Collection::getMaxAttributeValue()
// ---------------------------------------------------------------------------

it('computes MAX over the full filtered set in getMaxAttributeValue despite stacked order and limit', function () {
    catalogStrictGroupByFixture();
    $collection = catalogStrictGroupByCollection();

    // Ungrouped MAX aggregate: one row, unaffected by the stacked ORDER/LIMIT.
    $max = $collection->getMaxAttributeValue('price');
    expect($max)->not->toBeNull();
    expect((float) $max)->toBe(35.0);

    // The method works on a clone: the source collection stays intact.
    expect((int) $collection->getSize())->toBe(3);
});

it('returns null from getMaxAttributeValue when the collection matches nothing', function () {
    /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
    $collection = Mage::getResourceModel('catalog/product_collection');
    $collection->addAttributeToFilter('sku', CATALOG_SGB_SKU_PREFIX . 'missing');

    expect($collection->getMaxAttributeValue('price'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Mage_Catalog_Model_Resource_Product_Collection::getAttributeValueCount()
// ---------------------------------------------------------------------------

it('counts products per attribute value in getAttributeValueCount grouped by the underlying column', function () {
    catalogStrictGroupByFixture();
    $collection = catalogStrictGroupByCollection();

    // Normalize keys: decimal backends return '15.0000' on MySQL/PostgreSQL
    // and a native numeric on SQLite (which PHP truncates to an int array key).
    $counts = [];
    foreach ($collection->getAttributeValueCount('price') as $value => $count) {
        $counts[number_format((float) $value, 2, '.', '')] = (int) $count;
    }

    expect($counts)->toHaveCount(2);
    expect($counts['15.00'])->toBe(2);
    expect($counts['35.00'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Mage_Catalog_Model_Resource_Product_Collection::getAttributeValueCountByRange()
// ---------------------------------------------------------------------------

it('buckets products by the underlying range expression in getAttributeValueCountByRange', function () {
    catalogStrictGroupByFixture();

    // Older SQLite builds ship without the optional math functions.
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    try {
        $adapter->fetchOne('SELECT CEIL(1.2)');
    } catch (\Throwable $e) {
        $this->markTestSkipped('CEIL() is not available on this database build');
    }

    $collection = catalogStrictGroupByCollection();

    // Normalize keys: CEIL yields an int on MySQL, numeric on PostgreSQL, float on SQLite.
    $ranges = [];
    foreach ($collection->getAttributeValueCountByRange('price', 10) as $range => $count) {
        $ranges[(int) (float) $range] = (int) $count;
    }

    // CEIL((15.00+0.01)/10) = 2 (two products), CEIL((35.00+0.01)/10) = 4 (one product)
    expect($ranges)->toHaveCount(2);
    expect($ranges[2])->toBe(2);
    expect($ranges[4])->toBe(1);
});
