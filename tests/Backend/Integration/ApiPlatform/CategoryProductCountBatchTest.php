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

        public function record($sql, $bind): void
        {
            if (str_contains((string) $sql, 'COUNT(') && str_contains((string) $sql, 'catalog_category_product')) {
                $this->countQueries++;
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
        ->and($this->counter->countQueries)->toBe(1);
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
