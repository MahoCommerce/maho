<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Mage\Catalog\Api\CategoryProvider;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store);
    $this->db = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->db->beginTransaction();
    $config = Mage::getSingleton('eav/config');
    $categoryType = $config->getEntityType('catalog_category');
    $root = Mage::getModel('catalog/category')->load($store->getRootCategoryId());
    $makeCategory = function (int $parentId, string $parentPath, int $level, int $position, bool $anchor = false) use ($config, $categoryType): Mage_Catalog_Model_Category {
        $this->db->insert('catalog_category_entity', [
            'entity_type_id' => $categoryType->getId(),
            'attribute_set_id' => $categoryType->getDefaultAttributeSetId(),
            'parent_id' => $parentId,
            'path' => $parentPath,
            'position' => $position,
            'level' => $level,
            'children_count' => 0,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->db->update('catalog_category_entity', ['path' => "$parentPath/$id"], ['entity_id = ?' => $id]);
        foreach (['is_active' => 1, 'is_anchor' => (int) $anchor, 'url_path' => "count-batch-$id"] as $code => $value) {
            $attribute = $config->getAttribute('catalog_category', $code);
            $this->db->insert($attribute->getBackendTable(), [
                'entity_type_id' => $categoryType->getId(),
                'attribute_id' => $attribute->getId(),
                'store_id' => 0,
                'entity_id' => $id,
                'value' => $value,
            ]);
        }
        return Mage::getModel('catalog/category')->load($id);
    };
    $this->makeCategory = $makeCategory;
    $this->parent = $makeCategory((int) $root->getId(), $root->getPath(), 2, 0);
    $this->categories = [];
    for ($i = 0; $i < 3; $i++) {
        $this->categories[] = $makeCategory((int) $this->parent->getId(), $this->parent->getPath(), 3, $i, $i === 1);
    }
    $this->grandchild = $makeCategory((int) $this->categories[1]->getId(), $this->categories[1]->getPath(), 4, 0);
    $productType = $config->getEntityType('catalog_product');
    foreach ([$this->categories[0], $this->categories[1], $this->grandchild] as $i => $category) {
        $this->db->insert('catalog_product_entity', [
            'entity_type_id' => $productType->getId(),
            'attribute_set_id' => $productType->getDefaultAttributeSetId(),
            'sku' => 'count-batch-' . $category->getId(),
        ]);
        $this->db->insert('catalog_category_product', [
            'category_id' => $category->getId(),
            'product_id' => (int) $this->db->lastInsertId(),
            'position' => 0,
        ]);
    }
    $this->counter = new class {
        public int $countQueries = 0;
        public int $childrenQueries = 0;

        public function record($sql, $bind): void
        {
            if (str_contains((string) $sql, 'COUNT(') && str_contains((string) $sql, 'catalog_category_product')) {
                $this->countQueries++;
            }
            if (str_contains((string) $sql, 'm.position ASC')) {
                $this->childrenQueries++;
            }
        }
    };
    $this->previousHook = $this->db->setQueryHook(['object' => $this->counter, 'method' => 'record']);
});

afterEach(function (): void {
    $this->db->setQueryHook($this->previousHook);
    $this->db->rollBack();
});

it('loads direct product counts once for a category page including anchors and empty categories', function (): void {
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId()],
    ]));

    expect(array_column($items, 'productCount'))->toBe([1, 1, 0])
        ->and($this->counter->countQueries)->toBe(1)
        ->and($this->counter->childrenQueries)->toBe(1);
});

it('batches child product counts in a single category response', function (): void {
    $item = new CategoryProvider()->provide(new Get(), ['id' => $this->parent->getId()]);

    expect($item->productCount)->toBe(0)
        ->and(array_column($item->children, 'productCount'))->toBe([1, 1, 0])
        ->and($this->counter->countQueries)->toBe(2);
});

it('does not count products for an empty category page', function (): void {
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->categories[2]->getId()],
    ]));

    expect($items)->toBe([])
        ->and($this->counter->countQueries)->toBe(0);
});

it('keeps pagination when loading product counts', function (): void {
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId(), 'pageSize' => 1, 'page' => 2],
    ]));

    expect(array_column($items, 'id'))->toBe([(int) $this->categories[1]->getId()])
        ->and(array_column($items, 'productCount'))->toBe([1])
        ->and($this->counter->countQueries)->toBe(1);
});

it('preserves child activity overrides, path matching and complete DTOs in two stores', function (): void {
    $store = Mage::app()->getStore();
    $this->db->insert('core_store', [
        'code' => 'category_batch_fixture',
        'website_id' => $store->getWebsiteId(),
        'group_id' => $store->getGroupId(),
        'name' => 'Category batch fixture',
        'sort_order' => 99,
        'is_active' => 1,
    ]);
    $secondStoreId = (int) $this->db->lastInsertId();
    Mage::app()->reinitStores();
    $parent = $this->categories[1];
    $child = ($this->makeCategory)((int) $parent->getId(), $parent->getPath(), 4, -1);
    $inactive = ($this->makeCategory)((int) $parent->getId(), $parent->getPath(), 4, 2);
    $activeAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'is_active');
    $this->db->update($activeAttribute->getBackendTable(), ['value' => 0], [
        'entity_id = ?' => $inactive->getId(),
        'attribute_id = ?' => $activeAttribute->getId(),
    ]);
    foreach ([$child->getId() => 0, $inactive->getId() => 1] as $id => $value) {
        $this->db->insert($activeAttribute->getBackendTable(), [
            'entity_type_id' => $activeAttribute->getEntityTypeId(),
            'attribute_id' => $activeAttribute->getId(),
            'store_id' => $secondStoreId,
            'entity_id' => $id,
            'value' => $value,
        ]);
    }
    // The existing method follows path and level even when parent_id disagrees.
    $this->db->update('catalog_category_entity', ['parent_id' => $this->categories[0]->getId()], [
        'entity_id = ?' => $this->grandchild->getId(),
    ]);

    foreach ([(int) $store->getId(), $secondStoreId] as $storeId) {
        Mage::app()->setCurrentStore($storeId);
        $categories = array_map(static fn($category) => (clone $category)->setStoreId($storeId), $this->categories);
        $expected = [];
        foreach ($categories as $category) {
            $expected[(int) $category->getId()] = array_map(intval(...), $category->getResource()->getChildren($category, false));
        }
        $this->counter->childrenQueries = 0;
        $actual = $parent->getResource()->getChildrenForCategories($categories);
        expect($actual)->toBe($expected)
            ->and($this->counter->childrenQueries)->toBe(1);
        $expectedIds = $storeId === (int) $store->getId()
            ? [(int) $child->getId(), (int) $this->grandchild->getId()]
            : [(int) $this->grandchild->getId(), (int) $inactive->getId()];
        expect($actual[(int) $parent->getId()])->toBe($expectedIds);

        foreach ($categories as $category) {
            $provider = new CategoryProvider();
            $expectedDto = $provider->mapToDto(clone $category, true);
            $actualDto = $provider->mapToDto(clone $category, true, childrenIds: $actual[(int) $category->getId()]);
            expect(json_encode($actualDto, JSON_THROW_ON_ERROR))->toBe(json_encode($expectedDto, JSON_THROW_ON_ERROR));
        }
    }
});

it('uses the supplied category paths and bounds large batches', function (): void {
    $categories = [];
    $expected = [];
    for ($i = 0; $i < 101; $i++) {
        $category = clone $this->categories[0];
        $category->setId(1000000 + $i);
        $category->setPath($this->categories[1]->getPath());
        $categories[] = $category;
        $expected[(int) $category->getId()] = [(int) $this->grandchild->getId()];
    }
    $this->counter->childrenQueries = 0;
    expect($this->parent->getResource()->getChildrenForCategories($categories))->toBe($expected)
        ->and($this->counter->childrenQueries)->toBe(2);
});

it('skips child queries for an empty batch', function (): void {
    expect($this->parent->getResource()->getChildrenForCategories([]))->toBe([])
        ->and($this->counter->childrenQueries)->toBe(0);
});
