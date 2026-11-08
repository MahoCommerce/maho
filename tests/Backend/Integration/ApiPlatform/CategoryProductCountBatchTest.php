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
        ->and($this->counter->countQueries)->toBe(2)
        ->and($this->counter->childrenQueries)->toBe(2);
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

class CategoryBatchRewrittenModel extends Mage_Catalog_Model_Category
{
    public static int $countCalls = 0;
    public static int $childrenCalls = 0;

    #[\Override]
    public function getProductCount()
    {
        self::$countCalls++;
        return 701;
    }

    #[\Override]
    public function getChildren()
    {
        self::$childrenCalls++;
        return '';
    }
}

class CategoryBatchRewrittenResource extends Mage_Catalog_Model_Resource_Category
{
    public static int $countCalls = 0;
    public static int $childrenCalls = 0;

    #[\Override]
    public function getProductCount($category)
    {
        self::$countCalls++;
        return 702;
    }

    #[\Override]
    public function getChildren($category, $recursive = true)
    {
        self::$childrenCalls++;
        return parent::getChildren($category, $recursive);
    }
}

class CategoryBatchRewrittenChildrenSelect extends Mage_Catalog_Model_Resource_Category
{
    public static int $selectCalls = 0;

    #[\Override]
    protected function _getChildrenIdSelect($category, $recursive = true)
    {
        self::$selectCalls++;
        return parent::_getChildrenIdSelect($category, $recursive)->where('1 = 0');
    }
}

class CategoryBatchRewrittenCollection extends Mage_Catalog_Model_Resource_Category_Collection {}

function categoryBatchRewrite(string $group, string $model, string $class): void
{
    $config = Mage::getConfig();
    $config->setNode("global/models/$group/rewrite/$model", $class);
    new ReflectionProperty(Mage_Core_Model_Config::class, '_classNameCache')->setValue($config, []);
    if ($group === 'catalog_resource' && $model === 'category') {
        Mage::unregister('_resource_singleton/catalog/category');
    }
}

it('keeps rewritten category model getters in collection responses', function (): void {
    categoryBatchRewrite('catalog', 'category', CategoryBatchRewrittenModel::class);
    CategoryBatchRewrittenModel::$countCalls = 0;
    CategoryBatchRewrittenModel::$childrenCalls = 0;
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId()],
    ]));

    expect(array_column($items, 'productCount'))->toBe([701, 701, 701])
        ->and(array_column($items, 'childrenIds'))->toBe([[], [], []])
        ->and(CategoryBatchRewrittenModel::$countCalls)->toBe(3)
        ->and(CategoryBatchRewrittenModel::$childrenCalls)->toBe(3)
        ->and($this->counter->countQueries)->toBe(0);
});

it('keeps rewritten resource getters for collection and nested child responses', function (): void {
    categoryBatchRewrite('catalog_resource', 'category', CategoryBatchRewrittenResource::class);
    CategoryBatchRewrittenResource::$countCalls = 0;
    CategoryBatchRewrittenResource::$childrenCalls = 0;
    $provider = new CategoryProvider();
    $items = iterator_to_array($provider->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId()],
    ]));
    expect(array_column($items, 'productCount'))->toBe([702, 702, 702])
        ->and(array_column($items, 'childrenIds'))->toBe([[], [(int) $this->grandchild->getId()], []])
        ->and(CategoryBatchRewrittenResource::$countCalls)->toBe(3)
        ->and(CategoryBatchRewrittenResource::$childrenCalls)->toBe(3)
        ->and($this->counter->countQueries)->toBe(0);

    CategoryBatchRewrittenResource::$countCalls = 0;
    CategoryBatchRewrittenResource::$childrenCalls = 0;
    $item = $provider->provide(new Get(), ['id' => $this->parent->getId()]);
    expect($item->productCount)->toBe(702)
        ->and(array_column($item->children, 'productCount'))->toBe([702, 702, 702])
        ->and(CategoryBatchRewrittenResource::$countCalls)->toBe(4)
        ->and(CategoryBatchRewrittenResource::$childrenCalls)->toBe(4);
});

it('keeps rewritten child-select predicates', function (): void {
    categoryBatchRewrite('catalog_resource', 'category', CategoryBatchRewrittenChildrenSelect::class);
    CategoryBatchRewrittenChildrenSelect::$selectCalls = 0;
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId()],
    ]));

    expect(array_column($items, 'productCount'))->toBe([1, 1, 0])
        ->and(array_column($items, 'childrenIds'))->toBe([[], [], []])
        ->and(CategoryBatchRewrittenChildrenSelect::$selectCalls)->toBe(3)
        ->and($this->counter->countQueries)->toBe(3);
});

it('keeps the original loading path for inherited category collections', function (): void {
    categoryBatchRewrite('catalog_resource', 'category_collection', CategoryBatchRewrittenCollection::class);
    $items = iterator_to_array(new CategoryProvider()->provide(new GetCollection(), [], [
        'filters' => ['parentId' => $this->parent->getId()],
    ]));

    expect(array_column($items, 'productCount'))->toBe([1, 1, 0])
        ->and(array_column($items, 'childrenIds'))->toBe([[], [(int) $this->grandchild->getId()], []])
        ->and($this->counter->countQueries)->toBe(3)
        ->and($this->counter->childrenQueries)->toBe(3);
});

it('keeps literal and wildcard path matching for noncanonical category paths', function (): void {
    $resource = $this->parent->getResource();
    foreach ([
        ['1/2/123', '1/2/123/child'],
        ['1/2/123', '1/2/1234/child'],
        ['Custom/Branch', 'custom/branch/child'],
        ['1/2/%', '1/2/anything/child'],
        ['1/2/_', '1/2/a/child'],
        ['1/2/*', '1/2/*/child'],
        ['1//2', '1//2/child'],
        ["1/2\n", "1/2\n/child"],
    ] as [$path, $childPath]) {
        $parent = clone $this->categories[1];
        $parent->setPath($path);
        $this->db->update('catalog_category_entity', ['path' => $childPath], [
            'entity_id = ?' => $this->grandchild->getId(),
        ]);
        $expected = array_map(intval(...), $resource->getChildren($parent, false));
        expect($resource->getChildrenForCategories([$parent]))->toBe([(int) $parent->getId() => $expected]);
    }
});

it('orders children with equal positions by ID', function (): void {
    $parent = $this->categories[1];
    $early = ($this->makeCategory)((int) $parent->getId(), $parent->getPath(), 4, -1);
    $tied = ($this->makeCategory)((int) $parent->getId(), $parent->getPath(), 4, 0);
    $late = ($this->makeCategory)((int) $parent->getId(), $parent->getPath(), 4, 1);
    $resource = $parent->getResource();
    $actual = $resource->getChildrenForCategories([$parent])[(int) $parent->getId()];
    $expected = array_map(intval(...), $resource->getChildren($parent, false));

    expect($actual)->toBe($expected)
        ->and($actual)->toBe([(int) $early->getId(), (int) $this->grandchild->getId(), (int) $tied->getId(), (int) $late->getId()]);
});

it('loads identical repeated parent inputs once even across batch boundaries', function (): void {
    $parent = $this->categories[1];
    $expected = [(int) $parent->getId() => [(int) $this->grandchild->getId()]];
    $this->counter->childrenQueries = 0;

    expect($parent->getResource()->getChildrenForCategories(array_fill(0, 101, $parent)))->toBe($expected)
        ->and($this->counter->childrenQueries)->toBe(1);
});
