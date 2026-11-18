<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The data of the tile view of a category (issue #1624): the tiles are editable only when the category sorts by
 * position, and then they show the order of the storefront with the sales of the last days.
 */
beforeEach(function (): void {
    $resource = Mage::getSingleton('core/resource');
    $this->db = $resource->getConnection('core_write');
    $this->db->beginTransaction();
    $this->tables = [
        'product' => $resource->getTableName('catalog/product'),
        'link' => $resource->getTableName('catalog/category_product'),
        'sales' => $resource->getTableName('sales/bestsellers_aggregated_rolling'),
        'stock' => $resource->getTableName('cataloginventory/stock_status'),
    ];
    $store = Mage::app()->getDefaultStoreView();
    $this->storeId = (int) $store->getId();
    $this->rootId = (int) $store->getRootCategoryId();
    $productType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $first = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($this->tables['product'], new Maho\Db\Expr('MAX(entity_id)')));
    $this->ids = [];
    foreach ([0, 1, 2] as $offset) {
        $this->ids[$offset] = $first + $offset;
        $this->db->insert($this->tables['product'], [
            'entity_id' => $first + $offset, 'entity_type_id' => $productType->getId(),
            'attribute_set_id' => $productType->getDefaultAttributeSetId(),
            'type_id' => 'simple', 'sku' => 'merchandiser-' . $first . '-' . $offset,
        ]);
        $this->db->insert($this->tables['link'], [
            'category_id' => $this->rootId, 'product_id' => $first + $offset, 'position' => $offset,
        ]);
        $this->db->insert($this->tables['stock'], [
            'product_id' => $first + $offset, 'website_id' => $store->getWebsiteId(), 'stock_id' => 1,
            'qty' => 5, 'stock_status' => $offset === 0 ? 0 : 1,
        ]);
    }
    $this->merchandiser = Mage::getModel('catalog/category_merchandiser');
});

afterEach(function (): void {
    $this->db->rollBack();
});

function merchandiserCategory(int $storeId, int $rootId, array $data = []): Mage_Catalog_Model_Category
{
    return Mage::getModel('catalog/category')->setStoreId($storeId)->load($rootId)->addData($data);
}

it('lets the merchant set the order by hand only when the category sorts by position', function (?string $sort, bool $isManual) {
    $category = merchandiserCategory($this->storeId, $this->rootId, ['default_sort_by' => $sort, 'available_sort_by' => null]);

    expect($this->merchandiser->isManual($category))->toBe($isManual);
})->with([
    'position' => ['position', true],
    'bestsellers' => ['bestsellers', false],
    'newest' => ['newest', false],
    'price' => ['price', false],
]);

it('reads the sort of the store configuration when the category uses the config settings', function (string $configured, bool $isManual) {
    Mage::app()->getStore($this->storeId)->setConfig(Mage_Catalog_Model_Config::XML_PATH_LIST_DEFAULT_SORT_BY, $configured);
    $category = merchandiserCategory($this->storeId, $this->rootId, ['default_sort_by' => null, 'available_sort_by' => null]);

    expect($this->merchandiser->isManual($category))->toBe($isManual);
})->with([
    'position' => ['position', true],
    'bestsellers' => ['bestsellers', false],
]);

it('reads the sort of the store view before the sort of the default scope', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'default_sort_by');
    foreach ([0 => 'position', $this->storeId => 'bestsellers'] as $storeId => $value) {
        $this->db->insertOnDuplicate($attribute->getBackend()->getTable(), [
            'entity_id' => $this->rootId,
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id' => $attribute->getId(),
            'store_id' => $storeId,
            'value' => $value,
        ], ['value']);
    }

    expect($this->merchandiser->isManual(merchandiserCategory(0, $this->rootId, ['available_sort_by' => null])))->toBeTrue()
        ->and($this->merchandiser->isManual(merchandiserCategory($this->storeId, $this->rootId, ['available_sort_by' => null])))->toBeFalse();
});

it('lists the products in the order of the storefront, and the products that are out of stock last when the category says so', function () {
    [$a, $b, $c] = $this->ids;
    $this->db->insertMultiple($this->tables['sales'], [
        ['product_id' => $a, 'store_id' => $this->storeId, 'qty_ordered' => 9, 'revenue' => 90],
        ['product_id' => $b, 'store_id' => $this->storeId, 'qty_ordered' => 4, 'revenue' => 40],
    ]);
    $ours = static fn(array $order): array => array_values(array_intersect($order, [$a, $b, $c]));

    $category = merchandiserCategory($this->storeId, $this->rootId, ['default_sort_by' => 'bestsellers', 'available_sort_by' => null]);
    expect($ours($this->merchandiser->getStorefrontOrder($category)))->toBe([$a, $b, $c]);

    $category->setData('out_of_stock_last', 1);
    expect($ours($this->merchandiser->getStorefrontOrder($category)))->toBe([$b, $c, $a]);
});

it('puts the products that are out of stock last in the default scope, with the stock of the default store view', function () {
    [$a, $b, $c] = $this->ids;
    $this->db->insertMultiple($this->tables['sales'], [
        ['product_id' => $a, 'store_id' => 0, 'qty_ordered' => 9, 'revenue' => 90],
        ['product_id' => $b, 'store_id' => 0, 'qty_ordered' => 4, 'revenue' => 40],
    ]);
    $ours = static fn(array $order): array => array_values(array_intersect($order, [$a, $b, $c]));

    $category = merchandiserCategory(0, $this->rootId, ['default_sort_by' => 'bestsellers', 'available_sort_by' => null, 'out_of_stock_last' => 1]);

    expect($ours($this->merchandiser->getStorefrontOrder($category)))->toBe([$b, $c, $a]);
});

/**
 * @return array<string, mixed> the JSON answer of the tiles action
 */
function merchandiserTilesAnswer(int $categoryId, int $storeId, array $post): array
{
    $request = new Mage_Core_Controller_Request_Http();
    $request->setParam('id', $categoryId);
    $request->setParam('store', $storeId);
    $request->setPost($post);
    $response = new Mage_Core_Controller_Response_Http();
    Mage::unregister('category');
    Mage::unregister('current_category');
    new Mage_Adminhtml_Catalog_CategoryController($request, $response)->tilesAction();
    Mage::unregister('category');
    Mage::unregister('current_category');

    return Mage::helper('core')->jsonDecode($response->getBody());
}

it('sends the order of the storefront once for a category that sorts by something else, and then pages the posted order', function () {
    [$a, $b, $c] = $this->ids;
    $this->db->insertMultiple($this->tables['sales'], [
        ['product_id' => $a, 'store_id' => $this->storeId, 'qty_ordered' => 9, 'revenue' => 90],
        ['product_id' => $b, 'store_id' => $this->storeId, 'qty_ordered' => 4, 'revenue' => 40],
    ]);
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'default_sort_by');
    $this->db->insertOnDuplicate($attribute->getBackend()->getTable(), [
        'entity_id' => $this->rootId,
        'entity_type_id' => $attribute->getEntityTypeId(),
        'attribute_id' => $attribute->getId(),
        'store_id' => $this->storeId,
        'value' => 'bestsellers',
    ], ['value']);

    $first = merchandiserTilesAnswer($this->rootId, $this->storeId, []);
    $second = merchandiserTilesAnswer($this->rootId, $this->storeId, ['ids' => "$c,$a,$b", 'offset' => 0, 'limit' => 100]);

    expect(array_values(array_intersect($first['order'], [$a, $b, $c])))->toBe([$a, $b, $c])
        ->and(array_column($second['tiles'], 'id'))->toBe([$c, $a, $b])
        ->and($second['order'])->toBeNull();
});

it('gives each tile the stock status and the sales of the last days', function () {
    [$a, $b] = $this->ids;
    $this->db->insert($this->tables['sales'], ['product_id' => $a, 'store_id' => $this->storeId, 'qty_ordered' => 9, 'revenue' => 90]);
    $category = merchandiserCategory($this->storeId, $this->rootId);

    $tiles = $this->merchandiser->getTiles($category, [$b, $a]);

    expect(array_column($tiles, 'id'))->toBe([$b, $a])
        ->and($tiles[0]['in_stock'])->toBeTrue()
        ->and($tiles[0]['qty_sold'])->toBe(0.0)
        ->and($tiles[1]['in_stock'])->toBeFalse()
        ->and($tiles[1]['qty_sold'])->toBe(9.0)
        ->and($tiles[1]['revenue'])->toContain('90.00')
        ->and($tiles[1]['sku'])->toBe('merchandiser-' . $a . '-0');
});

it('keeps the order of the posted IDs when it filters by SKU or by stock', function () {
    [$a, $b, $c] = $this->ids;
    $ids = [$c, $a, $b];

    expect($this->merchandiser->filter($ids, $this->storeId))->toBe($ids)
        ->and($this->merchandiser->filter($ids, $this->storeId, 'merchandiser-' . $a . '-0'))->toBe([$a])
        ->and($this->merchandiser->filter($ids, $this->storeId, 'merchandiser-' . $a))->toBe([$c, $a, $b])
        ->and($this->merchandiser->filter($ids, $this->storeId, '', true))->toBe([$a])
        ->and($this->merchandiser->filter($ids, $this->storeId, 'no-such-product'))->toBe([]);
});

it('finds the products that the category does not hold yet', function () {
    [$a, $b, $c] = $this->ids;

    expect($this->merchandiser->search('merchandiser-' . $a, [$b], $this->storeId, 10))->toBe([$a, $c]);
});

it('reads the page size of the storefront in the mode that it opens', function (string $mode, int $perPage) {
    $store = Mage::app()->getStore($this->storeId);
    $store->setConfig('catalog/frontend/list_mode', $mode);
    $store->setConfig('catalog/frontend/grid_per_page', '12');
    $store->setConfig('catalog/frontend/list_per_page', '10');

    expect($this->merchandiser->getPerPage(merchandiserCategory($this->storeId, $this->rootId)))->toBe($perPage);
})->with([
    'grid first' => ['grid-list', 12],
    'list first' => ['list-grid', 10],
]);

it('uses the first allowed page size when the default page size is not allowed', function () {
    $store = Mage::app()->getStore($this->storeId);
    $store->setConfig('catalog/frontend/list_mode', 'grid');
    $store->setConfig('catalog/frontend/grid_per_page', '24');
    $store->setConfig('catalog/frontend/grid_per_page_values', '12,36');

    expect($this->merchandiser->getPerPage(merchandiserCategory($this->storeId, $this->rootId)))->toBe(12);
});
