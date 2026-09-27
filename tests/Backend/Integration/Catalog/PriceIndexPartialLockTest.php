<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A partial price reindex stages rows in _tmp tables that all processes share. Two checkouts that
 * reindex at the same time copy the rows of each other and fail on a duplicate key (issue #1473).
 */
it('does not touch the shared tmp tables while another process holds their lock', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $mainTable = $resource->getTableName('catalog/product_index_price');
    $tmpTable = $mainTable . '_tmp';

    $productId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from(['i' => $mainTable], ['entity_id'])
            ->join(['e' => $resource->getTableName('catalog/product')], 'e.entity_id = i.entity_id', [])
            ->where('e.type_id = ?', 'simple')
            ->limit(1),
    );
    expect($productId)->toBeGreaterThan(0);

    // A row that the partial reindex of another process staged and did not copy yet
    $stagedRow = $adapter->fetchRow(
        $adapter->select()->from($mainTable)->where('entity_id = ?', $productId)->limit(1),
    );
    $adapter->delete($tmpTable);
    $adapter->insert($tmpTable, $stagedRow);

    $lock = Mage::getSingleton('core/lock');
    $lock->acquire('catalog_product_index_price_tmp');
    try {
        expect(fn() => Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds([$productId]))
            ->toThrow(RuntimeException::class);
        expect((int) $adapter->fetchOne($adapter->select()->from($tmpTable, 'COUNT(*)')))->toBe(1);
    } finally {
        $lock->release('catalog_product_index_price_tmp');
        $adapter->delete($tmpTable);
    }

    Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds([$productId]);
    expect($lock->isHeld('catalog_product_index_price_tmp'))->toBeFalse();
});
