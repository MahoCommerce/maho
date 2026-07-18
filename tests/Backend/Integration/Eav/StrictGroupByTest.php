<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    Mage::setIsDeveloperMode(true);
});

it('loads the catalog category collection with attribute joins under strict GROUP BY', function () {
    // Reproduces the failure from issue #688: when a category collection
    // is loaded with addAttributeToSelect for a non-admin store, the EAV
    // attribute loader joins t_d/t_s tables for "default vs store" value
    // resolution and groups by (entity_id, attribute_id). Under
    // ONLY_FULL_GROUP_BY, t_d.value / t_s.value / t_s.attribute_id were
    // non-grouped non-aggregated columns until Mysql helper's
    // wrapForGroupBy() was made dev-mode-aware.
    $collection = Mage::getResourceModel('catalog/category_collection');
    $collection
        ->addAttributeToSelect(['name', 'is_active', 'url_key'])
        ->setStore(1);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
    expect($collection->getSize())->toBeGreaterThan(0);
});

it('loads the catalog product collection with addAttributeToSelect under strict GROUP BY', function () {
    // The product collection uses the same EAV attribute-loader code path.
    $collection = Mage::getResourceModel('catalog/product_collection');
    $collection
        ->addAttributeToSelect(['name', 'price', 'sku'])
        ->setStore(1)
        ->setPageSize(5);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

it('loads the customer collection with addAttributeToSelect under strict GROUP BY', function () {
    $collection = Mage::getResourceModel('customer/customer_collection');
    $collection
        ->addAttributeToSelect(['firstname', 'lastname', 'email'])
        ->setPageSize(5);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Helpers for the attribute collection fixtures (addHasOptionsFilter and
// setAttributeSetFilter regression tests). Prefixed to avoid collisions with
// helper functions defined in other Pest test files of the same suite.
// ---------------------------------------------------------------------------

/**
 * Insert a user-defined attribute row (plus optional option rows) directly.
 * Returns the new attribute_id.
 */
function eavStrictGroupByCreateAttribute(int $entityTypeId, string $code, string $frontendInput, int $optionCount = 0): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('eav/attribute'), [
        'entity_type_id' => $entityTypeId,
        'attribute_code' => $code,
        'backend_type'   => $frontendInput === 'select' ? 'int' : 'varchar',
        'frontend_input' => $frontendInput,
        'frontend_label' => 'Maho Strict GROUP BY Test ' . $code,
        'is_required'    => 0,
        'is_user_defined' => 1,
        'is_unique'      => 0,
    ]);
    $attributeId = (int) $adapter->lastInsertId($resource->getTableName('eav/attribute'));

    for ($i = 0; $i < $optionCount; $i++) {
        $adapter->insert($resource->getTableName('eav/attribute_option'), [
            'attribute_id' => $attributeId,
            'sort_order'   => $i,
        ]);
    }

    return $attributeId;
}

/**
 * Remove attribute fixture rows (options first, then the attribute).
 */
function eavStrictGroupByDeleteAttributes(array $attributeIds): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    foreach ($attributeIds as $attributeId) {
        $adapter->delete(
            $resource->getTableName('eav/attribute_option'),
            ['attribute_id = ?' => $attributeId],
        );
        $adapter->delete(
            $resource->getTableName('eav/attribute'),
            ['attribute_id = ?' => $attributeId],
        );
    }
}

it('excludes optionless select attributes via addHasOptionsFilter without strict GROUP BY errors', function () {
    $entityTypeId = (int) Mage::getSingleton('eav/config')
        ->getEntityType(Mage_Catalog_Model_Product::ENTITY)
        ->getId();

    $suffix = uniqid();
    $withOptionsCode = 'maho_gb_opts_' . $suffix;
    $noOptionsCode = 'maho_gb_noopts_' . $suffix;
    $textCode = 'maho_gb_text_' . $suffix;

    // Two option rows on the first attribute: under the old LEFT JOIN + GROUP BY
    // shape this produced duplicate main_table rows; the IN-subquery must dedup.
    $withOptionsId = eavStrictGroupByCreateAttribute($entityTypeId, $withOptionsCode, 'select', 2);
    $noOptionsId = eavStrictGroupByCreateAttribute($entityTypeId, $noOptionsCode, 'select', 0);
    $textId = eavStrictGroupByCreateAttribute($entityTypeId, $textCode, 'text', 0);

    try {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('eav/entity_attribute_collection');
        $collection
            ->addFieldToFilter('main_table.entity_type_id', $entityTypeId)
            ->setCodeFilter([$withOptionsCode, $noOptionsCode, $textCode])
            ->addHasOptionsFilter();

        // Regression guard: the fixed query shape has no GROUP BY and filters
        // through an attribute_id IN (subquery) predicate.
        $sql = $collection->getSelect()->assemble();
        expect(stripos($sql, 'GROUP BY'))->toBeFalse();
        expect(stripos($sql, 'IN ('))->not->toBeFalse();

        // Direct load: throws under ONLY_FULL_GROUP_BY / PostgreSQL if broken.
        $collection->load();

        $codes = [];
        foreach ($collection as $attribute) {
            $codes[] = $attribute->getAttributeCode();
        }

        // select + has options -> included; text (non-select) -> included;
        // select + user-defined + no options -> excluded.
        expect($codes)->toContain($withOptionsCode);
        expect($codes)->toContain($textCode);
        expect($codes)->not->toContain($noOptionsCode);

        // Dedup preserved: the attribute with two options appears exactly once,
        // and COUNT-based getSize() agrees with the loaded item count.
        expect(array_count_values($codes)[$withOptionsCode])->toBe(1);
        expect($collection->getSize())->toBe(count($collection->getItems()));
    } finally {
        eavStrictGroupByDeleteAttributes([$withOptionsId, $noOptionsId, $textId]);
    }
});

it('filters by an array of attribute set ids via subquery without strict GROUP BY errors', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $entityType = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY);
    $entityTypeId = (int) $entityType->getId();
    $defaultSetId = (int) $entityType->getDefaultAttributeSetId();

    // Create a second attribute set + group, and link an attribute that already
    // belongs to the default set into it. Under the old JOIN + GROUP BY shape
    // an attribute in both sets produced two joined rows; the IN-subquery must
    // return it exactly once.
    // Resolve the new ids by their unique names so the fixture does not depend
    // on engine-specific lastInsertId() semantics.
    $setName = 'Maho Strict GROUP BY Set ' . uniqid();
    $adapter->insert($resource->getTableName('eav/attribute_set'), [
        'entity_type_id'     => $entityTypeId,
        'attribute_set_name' => $setName,
        'sort_order'         => 999,
    ]);
    $newSetId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('eav/attribute_set'), ['attribute_set_id'])
            ->where('attribute_set_name = ?', $setName),
    );
    expect($newSetId)->toBeGreaterThan(0);

    $groupName = 'Maho Strict Group ' . uniqid();
    $adapter->insert($resource->getTableName('eav/attribute_group'), [
        'attribute_set_id'     => $newSetId,
        'attribute_group_name' => $groupName,
        'sort_order'           => 1,
    ]);
    $newGroupId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('eav/attribute_group'), ['attribute_group_id'])
            ->where('attribute_set_id = ?', $newSetId)
            ->where('attribute_group_name = ?', $groupName),
    );
    expect($newGroupId)->toBeGreaterThan(0);

    $linkedAttributeId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('eav/entity_attribute'), ['attribute_id'])
            ->where('attribute_set_id = ?', $defaultSetId)
            ->order('sort_order ASC')
            ->limit(1),
    );
    expect($linkedAttributeId)->toBeGreaterThan(0);

    $adapter->insert($resource->getTableName('eav/entity_attribute'), [
        'entity_type_id'     => $entityTypeId,
        'attribute_set_id'   => $newSetId,
        'attribute_group_id' => $newGroupId,
        'attribute_id'       => $linkedAttributeId,
        'sort_order'         => 1,
    ]);

    try {
        // Array branch with an attribute present in BOTH sets.
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('eav/entity_attribute_collection');
        $collection->setAttributeSetFilter([$defaultSetId, $newSetId]);

        $sql = $collection->getSelect()->assemble();
        expect(stripos($sql, 'GROUP BY'))->toBeFalse();
        expect(stripos($sql, 'IN ('))->not->toBeFalse();

        // load() throws "Item ... with the same id already exists" if the query
        // returns the double-linked attribute twice, so a clean load proves
        // dedup semantics were preserved.
        $collection->load();
        expect($collection->getItemById($linkedAttributeId))->not->toBeNull();
        expect($collection->getSize())->toBe(count($collection->getItems()));

        // Filtering by only the new set returns exactly the linked attribute.
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $onlyNewSet */
        $onlyNewSet = Mage::getResourceModel('eav/entity_attribute_collection');
        $onlyNewSet->setAttributeSetFilter([$newSetId]);
        $onlyNewSet->load();
        expect(count($onlyNewSet->getItems()))->toBe(1);
        expect((int) $onlyNewSet->getFirstItem()->getId())->toBe($linkedAttributeId);
        expect($onlyNewSet->getSize())->toBe(1);

        // Empty-array branch applies no filter and still loads.
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $unfiltered */
        $unfiltered = Mage::getResourceModel('eav/entity_attribute_collection');
        $unfiltered->setAttributeSetFilter([]);
        $unfiltered->setPageSize(5);
        $unfiltered->load();
        expect($unfiltered->isLoaded())->toBeTrue();
    } finally {
        $adapter->delete(
            $resource->getTableName('eav/entity_attribute'),
            ['attribute_set_id = ?' => $newSetId],
        );
        $adapter->delete(
            $resource->getTableName('eav/attribute_group'),
            ['attribute_group_id = ?' => $newGroupId],
        );
        $adapter->delete(
            $resource->getTableName('eav/attribute_set'),
            ['attribute_set_id = ?' => $newSetId],
        );
    }
});

it('loads the catalog product attribute collection with stacked filters (advanced-search shape) without strict GROUP BY errors', function () {
    // Mirrors Mage_CatalogSearch_Model_Advanced::getAttributes() plus the array
    // set filter: additional_table join (catalog_eav_attribute), COALESCE store
    // label, has-options subquery, and set-id subquery all stacked on one select.
    $entityType = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY);
    $defaultSetId = (int) $entityType->getDefaultAttributeSetId();

    /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection */
    $collection = Mage::getResourceModel('catalog/product_attribute_collection');
    $collection
        ->setAttributeSetFilter([$defaultSetId])
        ->addHasOptionsFilter()
        ->addStoreLabel(1)
        ->setOrder('main_table.attribute_id', 'asc');

    $sql = $collection->getSelect()->assemble();
    expect(stripos($sql, 'GROUP BY'))->toBeFalse();

    $collection->load();

    // The default product attribute set always contains system attributes
    // (name, sku, ...) which pass the is_user_defined = 0 branch of the filter.
    expect(count($collection->getItems()))->toBeGreaterThan(0);
    expect($collection->getSize())->toBe(count($collection->getItems()));
});

it('reports strict GROUP BY as required when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $helper = Mage::getResourceHelper('eav');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite) {
        // SQLite never enforces strict GROUP BY, regardless of developer mode.
        expect($helper->requiresStrictGroupBy())->toBeFalse();
    } else {
        // MySQL (ONLY_FULL_GROUP_BY in developer mode) and PostgreSQL (always).
        expect($helper->requiresStrictGroupBy())->toBeTrue();
    }
});

it('reports strict GROUP BY as not required when developer mode is off', function () {
    Mage::setIsDeveloperMode(false);
    $helper = Mage::getResourceHelper('eav');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($adapter instanceof \Maho\Db\Adapter\Pdo\Pgsql) {
        // PostgreSQL enforces strict GROUP BY natively, independent of dev mode.
        expect($helper->requiresStrictGroupBy())->toBeTrue();
    } else {
        // MySQL only enables ONLY_FULL_GROUP_BY in developer mode; SQLite never.
        expect($helper->requiresStrictGroupBy())->toBeFalse();
    }
});
