<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->productResource = Mage::getResourceSingleton('catalog/product');
    $this->adapter = $this->productResource->getWriteConnection();
    $this->productResource->beginTransaction();
    $resource = Mage::getSingleton('core/resource');
    $this->tables = [];
    foreach (['core/website', 'core/store_group', 'core/store', 'catalog/product', 'catalog/product_website', 'catalog/product_enabled_index'] as $alias) {
        $this->tables[$alias] = $resource->getTableName($alias);
    }
    $this->websiteIds = [];
    $this->storeIds = [];
    foreach (['a', 'b'] as $name) {
        $this->adapter->insert($this->tables['core/website'], [
            'code' => 'enabled_index_' . $name . '_' . uniqid(),
            'name' => 'Enabled index ' . $name,
        ]);
        $website = (int) $this->adapter->lastInsertId($this->tables['core/website']);
        $this->websiteIds[$name] = $website;
        $this->adapter->insert($this->tables['core/store_group'], [
            'website_id' => $website,
            'name' => 'Enabled index ' . $name,
            'root_category_id' => (int) Mage::app()->getStore(1)->getRootCategoryId(),
        ]);
        $group = (int) $this->adapter->lastInsertId($this->tables['core/store_group']);
        $this->adapter->insert($this->tables['core/store'], [
            'code' => 'enabled_index_' . $name . '_' . uniqid(),
            'website_id' => $website,
            'group_id' => $group,
            'name' => 'Enabled index ' . $name,
            'is_active' => 1,
        ]);
        $store = (int) $this->adapter->lastInsertId($this->tables['core/store']);
        $this->storeIds[$name] = $store;
        $this->adapter->update($this->tables['core/website'], ['default_group_id' => $group], ['website_id = ?' => $website]);
        $this->adapter->update($this->tables['core/store_group'], ['default_store_id' => $store], ['group_id = ?' => $group]);
    }
    Mage::app()->reinitStores();

    $entityType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $attributes = [
        'status' => Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'status'),
        'visibility' => Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'visibility'),
    ];
    $specifications = [
        'a' => [['a'], 1, 4, []],
        'b' => [['b'], 1, 4, []],
        'both' => [['a', 'b'], 1, 4, ['b' => ['visibility' => 2]]],
        'disabled' => [['a', 'b'], 2, 4, []],
        'override' => [['a', 'b'], 2, 4, ['a' => ['status' => 1, 'visibility' => 3]]],
        'missing_status' => [['a', 'b'], null, 4, ['a' => ['status' => 1]]],
        'missing_visibility' => [['a', 'b'], 1, null, ['a' => ['visibility' => 4]]],
        'unselected' => [['a', 'b'], 1, 4, []],
    ];
    $this->productIds = [];
    foreach ($specifications as $name => [$websites, $status, $visibility, $overrides]) {
        $this->adapter->insert($this->tables['catalog/product'], [
            'entity_type_id' => $entityType->getId(),
            'attribute_set_id' => $entityType->getDefaultAttributeSetId(),
            'type_id' => 'simple',
            'sku' => 'enabled-index-' . $name . '-' . uniqid(),
        ]);
        $productId = (int) $this->adapter->lastInsertId($this->tables['catalog/product']);
        $this->productIds[$name] = $productId;
        foreach (['status' => $status, 'visibility' => $visibility] as $code => $value) {
            $attribute = $attributes[$code];
            $base = ['entity_type_id' => $entityType->getId(), 'entity_id' => $productId, 'attribute_id' => $attribute->getId()];
            if ($value !== null) {
                $this->adapter->insert($attribute->getBackendTable(), $base + ['store_id' => 0, 'value' => $value]);
            }
            foreach ($overrides as $store => $values) {
                if (isset($values[$code])) {
                    $this->adapter->insert($attribute->getBackendTable(), $base + ['store_id' => $this->storeIds[$store], 'value' => $values[$code]]);
                }
            }
        }
        foreach ($websites as $website) {
            $this->adapter->insert($this->tables['catalog/product_website'], ['product_id' => $productId, 'website_id' => $this->websiteIds[$website]]);
        }
        foreach ($this->storeIds as $storeId) {
            $this->adapter->insert($this->tables['catalog/product_enabled_index'], ['product_id' => $productId, 'store_id' => $storeId, 'visibility' => 1]);
        }
    }
});

afterEach(function () {
    $this->productResource->rollBack();
    Mage::app()->reinitStores();
});

it('refreshes only enabled products assigned to the store website', function (string $store, string $selection) {
    $allProducts = $selection !== 'selected';
    $selected = array_values($this->productIds);
    if (!$allProducts) {
        $selected = array_values(array_diff($selected, [$this->productIds['unselected']]));
    }
    $storeId = $this->storeIds[$store];
    expect($this->productResource->refreshEnabledIndex(Mage::app()->getStore($storeId), $selection === 'empty' ? [] : ($allProducts ? null : $selected)))
        ->toBe($this->productResource);

    $actual = $this->adapter->fetchPairs(
        $this->adapter->select()
            ->from($this->tables['catalog/product_enabled_index'], ['product_id', 'visibility'])
            ->where('product_id IN (?)', array_values($this->productIds))
            ->where('store_id = ?', $storeId),
    );
    $names = $store === 'a' ? ['a' => 4, 'both' => 4, 'override' => 3] : ['b' => 4, 'both' => 2];
    $names['unselected'] = $allProducts ? 4 : 1;
    $expected = [];
    foreach ($names as $name => $visibility) {
        $expected[$this->productIds[$name]] = $visibility;
    }
    ksort($expected);
    ksort($actual);
    expect(array_map(intval(...), $actual))->toBe($expected);

    $otherStore = $this->storeIds[$store === 'a' ? 'b' : 'a'];
    $otherValues = $this->adapter->fetchCol(
        $this->adapter->select()
            ->from($this->tables['catalog/product_enabled_index'], ['visibility'])
            ->where('product_id IN (?)', array_values($this->productIds))
            ->where('store_id = ?', $otherStore),
    );
    expect(array_map(intval(...), $otherValues))->toBe(array_fill(0, count($this->productIds), 1));
})->with([
    'selected products, website A' => ['a', 'selected'],
    'selected products, website B' => ['b', 'selected'],
    'all products, website A' => ['a', 'all'],
    'all products, website B' => ['b', 'all'],
    'empty selection, website A' => ['a', 'empty'],
    'empty selection, website B' => ['b', 'empty'],
]);

it('preserves the explicit store and scalar product refresh', function (string $product, string $store, int $visibility) {
    $productId = $this->productIds[$product];
    $storeId = $this->storeIds[$store];
    $readIndex = function (): array {
        $rows = $this->adapter->fetchAll(
            $this->adapter->select()
                ->from($this->tables['catalog/product_enabled_index'], ['product_id', 'store_id', 'visibility'])
                ->where('product_id IN (?)', array_values($this->productIds))
                ->where('store_id IN (?)', array_values($this->storeIds))
                ->order(['product_id', 'store_id']),
        );
        $index = [];
        foreach ($rows as $row) {
            $index[$row['product_id'] . ':' . $row['store_id']] = (int) $row['visibility'];
        }
        return $index;
    };
    $expected = $readIndex();
    $expected[$productId . ':' . $storeId] = $visibility;

    expect($this->productResource->refreshEnabledIndex(Mage::app()->getStore($storeId), $productId))
        ->toBe($this->productResource);
    expect($readIndex())->toBe($expected);
})->with([
    'assigned store' => ['a', 'a', 4],
    'unassigned store' => ['a', 'b', 4],
    'store visibility override' => ['both', 'b', 2],
]);
