<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

/**
 * Flat catalog was removed. Nothing may register its indexers, its store config,
 * or its per-store tables again, and an install must not carry them over.
 */

uses(MahoBackendTestCase::class);

it('registers no flat catalog indexer', function () {
    $indexers = Mage::getConfig()->getNode(Mage_Index_Model_Process::XML_PATH_INDEXER_DATA);
    $codes = array_keys($indexers->asArray());

    expect($codes)->not->toContain('catalog_product_flat')
        ->and($codes)->not->toContain('catalog_category_flat');

    $indexer = Mage::getSingleton('index/indexer');
    expect($indexer->getProcessByCode('catalog_product_flat'))->toBeFalse()
        ->and($indexer->getProcessByCode('catalog_category_flat'))->toBeFalse();
});

it('exposes no flat catalog store config', function () {
    expect(Mage::getConfig()->getNode('default/catalog/frontend/flat_catalog_category'))->toBeFalse()
        ->and(Mage::getConfig()->getNode('default/catalog/frontend/flat_catalog_product'))->toBeFalse()
        ->and(class_exists('Mage_Catalog_Helper_Product_Flat'))->toBeFalse()
        ->and(class_exists('Mage_Catalog_Helper_Category_Flat'))->toBeFalse();
});

it('leaves no flat catalog table behind', function () {
    $tables = Mage::getSingleton('core/resource')->getConnection('core_read')->listTables();
    $flat = preg_grep('/catalog_(?:category|product)_flat/', $tables);

    expect(array_values($flat))->toBe([]);
});

it('loads a product collection from the EAV entity table on the frontend', function () {
    $collection = Mage::getResourceModel('catalog/product_collection')->setStoreId(1);
    $from = $collection->getSelect()->getPart(Maho\Db\Select::FROM);

    expect($from['e']['tableName'])->toBe($collection->getTable('catalog/product'));
});
