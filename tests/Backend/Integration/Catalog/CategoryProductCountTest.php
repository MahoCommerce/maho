<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $resource = Mage::getSingleton('core/resource');
    $this->db = $resource->getConnection('core_write');
    $this->db->beginTransaction();
    $this->categoryResource = Mage::getResourceSingleton('catalog/category');
    $this->categoryTable = $resource->getTableName('catalog/category');
    $this->productTable = $resource->getTableName('catalog/product');
    $this->categoryId = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($this->categoryTable, new Maho\Db\Expr('MAX(entity_id)')));
    $productId = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($this->productTable, new Maho\Db\Expr('MAX(entity_id)')));
    $categoryType = Mage::getSingleton('eav/config')->getEntityType('catalog_category');
    $productType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $this->store = Mage::app()->getDefaultStoreView();

    for ($offset = 0; $offset < 4; $offset++) {
        $id = $this->categoryId + $offset;
        $this->db->insert($this->categoryTable, [
            'entity_id' => $id, 'entity_type_id' => $categoryType->getId(),
            'attribute_set_id' => $categoryType->getDefaultAttributeSetId(),
            'parent_id' => $offset === 0 ? 1 : $this->categoryId,
            'path' => '1/' . $this->categoryId . ($offset === 0 ? '' : '/' . $id),
            'level' => $offset === 0 ? 1 : 2, 'position' => $offset,
            'children_count' => $offset === 0 ? 3 : 0,
        ]);
        foreach (['is_anchor' => $offset === 2 ? 0 : 1, 'is_active' => $offset === 1 ? 0 : 1] as $code => $value) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', $code);
            $this->db->insert($attribute->getBackend()->getTable(), [
                'entity_id' => $id, 'entity_type_id' => $categoryType->getId(),
                'attribute_id' => $attribute->getId(), 'store_id' => 0, 'value' => $value,
            ]);
        }
    }
    for ($offset = 0; $offset < 2; $offset++) {
        $this->db->insert($this->productTable, [
            'entity_id' => $productId + $offset, 'entity_type_id' => $productType->getId(),
            'attribute_set_id' => $productType->getDefaultAttributeSetId(),
            'type_id' => 'simple', 'sku' => 'category-count-' . $productId . '-' . $offset,
        ]);
    }
    $this->db->insert($resource->getTableName('catalog/product_website'), [
        'product_id' => $productId, 'website_id' => $this->store->getWebsiteId(),
    ]);
    foreach ([0 => [$productId], 1 => [$productId, $productId + 1], 2 => [$productId]] as $offset => $products) {
        foreach ($products as $id) {
            $this->db->insert($resource->getTableName('catalog/category_product'), [
                'category_id' => $this->categoryId + $offset, 'product_id' => $id, 'position' => 0,
            ]);
        }
    }
    $this->collection = Mage::getResourceModel('catalog/category_collection')
        ->setStoreId(0)
        ->addIdFilter(range($this->categoryId, $this->categoryId + 3))
        ->addAttributeToSelect(['is_anchor', 'is_active'])
        ->addAttributeToSort('entity_id')
        ->load();
    $this->items = array_values($this->collection->getItems());
});

afterEach(function (): void {
    Mage::unregister('_resource_singleton/catalog/category');
    Mage::register('_resource_singleton/catalog/category', $this->categoryResource);
    $this->db->rollBack();
});

/** Count statements around the native product-count operation, including read connections. */
function categoryCountQueries(callable $operation): int
{
    $counter = new class {
        public int $count = 0;

        public function record(string $sql, array $bind): void
        {
            $this->count++;
        }
    };
    $adapters = [];
    foreach (Mage::getSingleton('core/resource')->getConnections() as $adapter) {
        if ($adapter && !isset($adapters[spl_object_id($adapter)])) {
            $hook = new class ($counter) {
                public mixed $previous = null;

                public function __construct(private readonly object $counter) {}

                public function record(string $sql, array $bind): void
                {
                    if ($this->previous) {
                        $this->previous['object']->{$this->previous['method']}($sql, $bind);
                    }
                    $this->counter->record($sql, $bind);
                }
            };
            $hook->previous = $adapter->setQueryHook(['object' => $hook, 'method' => 'record']);
            $adapters[spl_object_id($adapter)] = [$adapter, $hook];
        }
    }
    try {
        $operation();
    } finally {
        foreach ($adapters as [$adapter, $hook]) {
            $adapter->setQueryHook($hook->previous);
        }
    }
    return $counter->count;
}

it('counts distinct products including inactive descendants with one statement per anchor', function (bool $websiteScope): void {
    $this->collection->setProductStoreId($websiteScope ? $this->store->getId() : 0);
    $queries = categoryCountQueries(fn() => $this->collection->loadProductCount($this->items));

    expect(array_map(static fn($item): int => (int) $item->getProductCount(), $this->items))
        ->toBe($websiteScope ? [1, 1, 1, 0] : [2, 2, 1, 0])
        ->and($queries)->toBe(4);
})->with([false, true]);

it('retains custom model and resource results and exceptions', function (string $kind, bool $throws): void {
    $extension = $kind === 'model'
        ? new class extends Mage_Catalog_Model_Category {
            public bool $throws = false;
            public int $calls = 0;

            #[\Override]
            public function getAllChildren($asArray = false)
            {
                $this->calls++;
                if ($this->throws) {
                    throw new RuntimeException('Custom category children');
                }
                return '';
            }
        }
    : new class extends Mage_Catalog_Model_Resource_Category {
        public bool $throws = false;
        public int $calls = 0;

        #[\Override]
        public function getAllChildren($category)
        {
            $this->calls++;
            if ($this->throws) {
                throw new RuntimeException('Custom category children');
            }
            return [];
        }
    };
    $extension->throws = $throws;
    if ($kind === 'resource') {
        Mage::unregister('_resource_singleton/catalog/category');
        Mage::register('_resource_singleton/catalog/category', $extension);
    }
    $item = $kind === 'model' ? $extension : new Mage_Catalog_Model_Category();
    $item->setData($this->items[0]->getData())->setProductCount(123);
    $load = fn() => $this->collection->loadProductCount([$item], false, true);
    if ($throws) {
        expect($load)->toThrow(RuntimeException::class, 'Custom category children');
    } else {
        $load();
    }
    expect($extension->calls)->toBe(1)
        ->and($item->getProductCount())->toBe($throws ? 123 : 0);
})->with([['model', false], ['model', true], ['resource', false], ['resource', true]]);

it('retains descendant loading for a custom collection', function (): void {
    $collection = new class extends Mage_Catalog_Model_Resource_Category_Collection {};
    $collection->setProductStoreId(0);
    $queries = categoryCountQueries(fn() => $collection->loadProductCount($this->items));
    expect(array_map(static fn($item): int => (int) $item->getProductCount(), $this->items))
        ->toBe([2, 2, 1, 0])
        ->and($queries)->toBe(7);
});

it('retains the descendant check for an anchor without an ID', function (mixed $id): void {
    $item = new Mage_Catalog_Model_Category();
    $item->setData($this->items[0]->getData())->setId($id)->setProductCount(123);
    $this->collection->setProductStoreId(0);
    $queries = categoryCountQueries(fn() => $this->collection->loadProductCount([$item], false, true));
    expect($item->getProductCount())->toBe(2)
        ->and($queries)->toBe(2);
})->with([null, 0, '0']);
