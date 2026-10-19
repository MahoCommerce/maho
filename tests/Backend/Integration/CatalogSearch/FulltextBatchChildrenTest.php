<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The fulltext indexer reads the children of all composite products of a batch
 * with one query for each product type, and writes the same text as before.
 */
function fulltextBatchAdapter(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function fulltextBatchInsertProduct(string $typeId, string $sku, string $name, int $websiteId): int
{
    $adapter = fulltextBatchAdapter();
    $entityType = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY);
    $entityTypeId = (int) $entityType->getId();

    $adapter->insert('catalog_product_entity', [
        'entity_type_id'   => $entityTypeId,
        'attribute_set_id' => (int) $entityType->getDefaultAttributeSetId(),
        'type_id'          => $typeId,
        'sku'              => $sku,
    ]);
    $productId = (int) $adapter->lastInsertId('catalog_product_entity');

    $eavConfig = Mage::getSingleton('eav/config');
    $adapter->insert('catalog_product_entity_varchar', [
        'entity_type_id' => $entityTypeId,
        'attribute_id'   => (int) $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId(),
        'store_id'       => 0,
        'entity_id'      => $productId,
        'value'          => $name,
    ]);
    $intValues = [
        'status'     => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ];
    foreach ($intValues as $code => $value) {
        $adapter->insert('catalog_product_entity_int', [
            'entity_type_id' => $entityTypeId,
            'attribute_id'   => (int) $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code)->getId(),
            'store_id'       => 0,
            'entity_id'      => $productId,
            'value'          => $value,
        ]);
    }
    $adapter->insert('catalog_product_website', ['product_id' => $productId, 'website_id' => $websiteId]);
    $adapter->insert('cataloginventory_stock_status', [
        'product_id'   => $productId,
        'website_id'   => $websiteId,
        'stock_id'     => Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID,
        'qty'          => 10,
        'stock_status' => Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK,
    ]);

    return $productId;
}

/** @return list<string> */
function fulltextBatchStatusText(): array
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'status');
    $attribute->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $text = $attribute->getSource()->getIndexOptionText(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    return $text ? [(string) $text] : [];
}

beforeEach(function () {
    $this->productIds = [];
});

afterEach(function () {
    if ($this->productIds) {
        $adapter = fulltextBatchAdapter();
        $adapter->delete('catalogsearch_fulltext', ['product_id IN (?)' => $this->productIds]);
        $adapter->delete('catalog_product_entity', ['entity_id IN (?)' => $this->productIds]);
    }
});

it('returns the same children for a batch as for each product alone', function () {
    $resource = Mage::getResourceModel('catalogsearch/fulltext');
    $products = fulltextBatchAdapter()->fetchAll(
        fulltextBatchAdapter()->select()
            ->from('catalog_product_entity', ['entity_id', 'type_id'])
            ->order('entity_id')
            ->limit(500),
    );
    expect($products)->not->toBeEmpty();

    $websiteId = (int) Mage::app()->getDefaultStoreView()->getWebsiteId();
    $batch = new ReflectionMethod($resource, '_getProductChildrenIdsBatch');
    $single = new ReflectionMethod($resource, '_getProductChildrenIds');

    $expected = [];
    foreach ($products as $productData) {
        $expected[$productData['entity_id']] = $single->invoke(
            $resource,
            $productData['entity_id'],
            $productData['type_id'],
            $websiteId,
        );
    }

    // Neither query has an ORDER BY, so PostgreSQL can return the children in another order
    $sorted = function (array $children): array {
        foreach ($children as &$ids) {
            if (is_array($ids)) {
                $ids = array_map(intval(...), $ids);
                sort($ids);
            }
        }
        return $children;
    };
    expect($sorted($batch->invoke($resource, $products, $websiteId)))->toBe($sorted($expected));
});

it('indexes the children of a configurable and a bundle product in one batch', function () {
    $adapter = fulltextBatchAdapter();
    $store = Mage::app()->getDefaultStoreView();
    $storeId = (int) $store->getId();
    $websiteId = (int) $store->getWebsiteId();
    $suffix = uniqid();

    $childA = fulltextBatchInsertProduct('simple', "batch-child-a-{$suffix}", 'Batch child Alpha', $websiteId);
    $childB = fulltextBatchInsertProduct('simple', "batch-child-b-{$suffix}", 'Batch child Beta', $websiteId);
    $configurable = fulltextBatchInsertProduct('configurable', "batch-conf-{$suffix}", 'Batch configurable', $websiteId);
    $bundle = fulltextBatchInsertProduct('bundle', "batch-bundle-{$suffix}", 'Batch bundle', $websiteId);
    $this->productIds = [$childA, $childB, $configurable, $bundle];

    foreach ([$childA, $childB] as $childId) {
        $adapter->insert('catalog_product_super_link', ['product_id' => $childId, 'parent_id' => $configurable]);
        $adapter->insert('catalog_product_relation', ['parent_id' => $configurable, 'child_id' => $childId]);
    }

    $adapter->insert('catalog_product_bundle_option', ['parent_id' => $bundle, 'required' => 1, 'type' => 'select']);
    $optionId = (int) $adapter->lastInsertId('catalog_product_bundle_option');
    $adapter->insert('catalog_product_bundle_option_value', [
        'option_id' => $optionId,
        'store_id'  => 0,
        'title'     => 'Batch option',
    ]);
    foreach ([$childA, $childB] as $position => $childId) {
        $adapter->insert('catalog_product_bundle_selection', [
            'option_id'         => $optionId,
            'parent_product_id' => $bundle,
            'product_id'        => $childId,
            'position'          => $position,
            'selection_qty'     => 1,
        ]);
        $adapter->insert('catalog_product_relation', ['parent_id' => $bundle, 'child_id' => $childId]);
    }

    Mage::getResourceModel('catalogsearch/fulltext')->rebuildIndex($storeId, [$configurable, $bundle]);

    $dataIndex = $adapter->fetchPairs(
        $adapter->select()
            ->from('catalogsearch_fulltext', ['product_id', 'data_index'])
            ->where('store_id = ?', $storeId)
            ->where('product_id IN (?)', [$configurable, $bundle]),
    );

    $status = fulltextBatchStatusText();
    $text = static fn(string $sku, string $name, array $options): string => implode('|', array_merge(
        [$sku],
        $status,
        $status,
        $status,
        [$name, 'Batch child Alpha', 'Batch child Beta'],
        $options,
        [(string) Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK],
    ));

    expect($dataIndex[$configurable] ?? null)->toBe($text("batch-conf-{$suffix}", 'Batch configurable', []))
        ->and($dataIndex[$bundle] ?? null)->toBe($text("batch-bundle-{$suffix}", 'Batch bundle', ['Batch option']));
});

it('reads the batch size from the configuration', function () {
    $resource = Mage::getResourceModel('catalogsearch/fulltext');

    expect($resource->getIndexBatchSize())->toBe(500);

    Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Resource_Fulltext::XML_PATH_INDEX_BATCH_SIZE, '0');
    expect($resource->getIndexBatchSize())->toBe(Mage_CatalogSearch_Model_Resource_Fulltext::DEFAULT_INDEX_BATCH_SIZE);

    Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Resource_Fulltext::XML_PATH_INDEX_BATCH_SIZE, '2');
    expect($resource->getIndexBatchSize())->toBe(2);
});
