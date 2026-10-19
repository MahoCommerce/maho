<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The category product indexer reads the products of an anchor category and the
 * products of its child categories with two selects. Each product gets one row.
 */
beforeEach(function () {
    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_category_product')->reindexEverything();
});

it('writes one row for each category, product and store', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/category_product_index');

    $rows = (int) $adapter->fetchOne($adapter->select()->from($table, [new Maho\Db\Expr('COUNT(*)')]));
    $distinct = (int) $adapter->fetchOne(
        $adapter->select()->from(
            ['k' => $adapter->select()->distinct()->from($table, ['category_id', 'product_id', 'store_id'])],
            [new Maho\Db\Expr('COUNT(*)')],
        ),
    );

    expect($rows)->toBeGreaterThan(0)
        ->and($rows)->toBe($distinct);
});

it('puts the products of the child categories in an anchor category', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $resource = Mage::getSingleton('core/resource');
    $indexTable = $resource->getTableName('catalog/category_product_index');
    $store = Mage::app()->getDefaultStoreView();
    $storeId = (int) $store->getId();
    $rootId = (int) $store->getRootCategoryId();

    $isAnchor = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'is_anchor');
    $anchors = $adapter->fetchPairs(
        $adapter->select()
            ->from(['ce' => $resource->getTableName('catalog/category')], ['entity_id', 'path'])
            ->join(
                ['ad' => $isAnchor->getBackend()->getTable()],
                $adapter->quoteInto('ad.entity_id = ce.entity_id AND ad.store_id = 0 AND ad.attribute_id = ?', (int) $isAnchor->getId()),
                [],
            )
            ->joinLeft(
                ['ast' => $isAnchor->getBackend()->getTable()],
                $adapter->quoteInto('ast.entity_id = ce.entity_id AND ast.attribute_id = ?', (int) $isAnchor->getId())
                    . $adapter->quoteInto(' AND ast.store_id = ?', $storeId),
                [],
            )
            ->where('ad.value = 1')
            ->where('ast.value_id IS NULL')
            ->where('ce.path LIKE ?', "%/{$rootId}/%"),
    );
    expect($anchors)->not->toBeEmpty();

    $checked = 0;
    foreach ($anchors as $anchorId => $path) {
        $expected = $adapter->fetchCol(
            $adapter->select()
                ->distinct()
                ->from(['i' => $indexTable], ['product_id'])
                ->join(['ce' => $resource->getTableName('catalog/category')], 'ce.entity_id = i.category_id', [])
                ->where('i.store_id = ?', $storeId)
                ->where('i.is_parent = 1')
                ->where('ce.path LIKE ? OR ce.entity_id = ' . (int) $anchorId, $path . '/%'),
        );
        $actual = $adapter->fetchCol(
            $adapter->select()
                ->from($indexTable, ['product_id'])
                ->where('store_id = ?', $storeId)
                ->where('category_id = ?', (int) $anchorId),
        );

        expect(array_values(array_diff($expected, $actual)))->toBe([]);
        $checked += count($expected);
    }

    expect($checked)->toBeGreaterThan(0);
});
