<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function reindexEntityRowCount(string $table, int $productId, string $column = 'product_id'): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName($table), [new Maho\Db\Expr('COUNT(*)')])
            ->where("{$column} = ?", $productId),
    );
}

function reindexEntityDeleteRows(string $table, int $productId, string $column = 'product_id'): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName($table), ["{$column} = ?" => $productId]);
}

beforeEach(function () {
    $this->product = createPriceWebsiteProduct('reindex-entity', 12.0);
});

afterEach(function () {
    $this->product->delete();
});

it('rebuilds the search index of the given products', function () {
    $productId = (int) $this->product->getId();
    reindexEntityDeleteRows('catalogsearch/fulltext', $productId);

    Mage::getSingleton('index/indexer')->getProcessByCode('catalogsearch_fulltext')->reindexEntity([$productId]);

    expect(reindexEntityRowCount('catalogsearch/fulltext', $productId))->toBeGreaterThan(0);
});

it('rebuilds the category index of the given products', function () {
    $productId = (int) $this->product->getId();
    reindexEntityDeleteRows('catalog/category_product_index', $productId);

    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_category_product')->reindexEntity([$productId]);

    expect(reindexEntityRowCount('catalog/category_product_index', $productId))->toBeGreaterThan(0);
});
