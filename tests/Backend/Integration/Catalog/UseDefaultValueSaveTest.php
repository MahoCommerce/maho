<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * "Use Default Value" puts the default value into the model and deletes the store view value at the save (#1857).
 * The model never holds false, so the observers of the save read the default value.
 */

const USE_DEFAULT_SKU = 'pest-use-default-value';

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', USE_DEFAULT_SKU) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => 'pest-use-default-%']) as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

/**
 * Save a product named "Pest Default Name", with the name "Pest Store Name" in the store view 1 when $storeName is
 * true. Return its ID.
 */
function useDefaultProduct(bool $storeName = true): int
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct();
    $product->setSku(USE_DEFAULT_SKU)
        ->setName('Pest Default Name')
        ->setPrice(10)
        ->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([1])
        ->setUrlKey('pest-use-default-' . uniqid())
        ->setStockData(['qty' => 5, 'is_in_stock' => 1]);
    $id = (int) $service->save($product)->getId();
    if ($storeName) {
        $service->save($service->getById($id, 1)->setName('Pest Store Name'));
    }
    return $id;
}

/**
 * Count the rows of the name of the product $id in the store view 1.
 */
function useDefaultStoreNameRows(int $id): int
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($attribute->getBackend()->getTable(), 'COUNT(*)')
            ->where('entity_id = ?', $id)
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('store_id = ?', 1),
    );
}

it('saves a product in a store view with the name on the default value', function () {
    $id = useDefaultProduct();
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $product->setStockData(['qty' => 5, 'is_in_stock' => 1]);
    $service->useDefault($product, ['name']);

    expect($product->getName())->toBe('Pest Default Name');

    $service->save($product);

    expect($product->getName())->toBe('Pest Default Name')
        ->and($product->getStockItem()->getProductName())->toBe('Pest Default Name')
        ->and(useDefaultStoreNameRows($id))->toBe(0)
        ->and($service->getById($id, 1)->getName())->toBe('Pest Default Name');
});

it('writes no store view value for a name that already uses the default value', function () {
    $id = useDefaultProduct(storeName: false);
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $service->useDefault($product, ['name']);
    $service->save($product->setWeight(2));

    expect(useDefaultStoreNameRows($id))->toBe(0)
        ->and($product->getName())->toBe('Pest Default Name');
});

it('saves a new store view value after a save on the default value', function () {
    $id = useDefaultProduct();
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $service->useDefault($product, ['name']);
    $service->save($product);

    expect($product->getUseDefault())->toBeNull();

    $service->save($product->setName('Pest New Store Name'));

    expect($service->getById($id, 1)->getName())->toBe('Pest New Store Name');

    $service->useDefault($product, ['name']);

    expect($product->getName())->toBe('Pest Default Name');
});

it('puts the inherited value back into a value that the caller changed before useDefaultValue()', function () {
    $id = useDefaultProduct(storeName: false);
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $product->setName('Pest Submitted Name')->useDefaultValue('name');

    expect($product->getName())->toBe('Pest Default Name');
});

it('puts the default value into a store view value that the same model saved', function () {
    $id = useDefaultProduct(storeName: false);
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $service->save($product->setName('Pest Store Name'));
    $service->useDefault($product, ['name']);

    expect($product->getName())->toBe('Pest Default Name');
});

it('puts null into a value with no default value after a save of the same model deleted it', function () {
    $id = useDefaultProduct(storeName: false);
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $service->save($service->getById($id, 1)->setData('meta_title', 'Pest Store Title'));

    $product = $service->getById($id, 1);
    $service->useDefault($product, ['meta_title']);
    $service->save($product);
    $service->save($product->setData('meta_title', 'Pest New Store Title'));
    $service->useDefault($product, ['meta_title']);

    expect($product->getMetaTitle())->toBeNull();
});

it('puts the default value into a product of a store view collection', function () {
    $id = useDefaultProduct();

    $product = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId(1)
        ->addAttributeToSelect('name')
        ->addIdFilter($id)
        ->getFirstItem();
    $product->useDefaultValue('name');

    expect($product->getName())->toBe('Pest Default Name');
});

it('keeps a value that a form field lists in use_default without the check', function () {
    $id = useDefaultProduct();
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $product->addData(['use_default' => ['price', 'name']]);
    $service->useDefault($product, []);
    $service->save($product);

    expect((float) $service->getById($id, 0)->getPrice())->toBe(10.0)
        ->and(useDefaultStoreNameRows($id))->toBe(1);
});

it('deletes a store view value that is equal to the default value', function () {
    $id = useDefaultProduct(storeName: false);
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $service->save($service->getById($id, 1)->setName('Pest Default Name'));

    expect(useDefaultStoreNameRows($id))->toBe(1);

    $product = $service->getById($id, 1);
    $service->useDefault($product, ['name']);
    $service->save($product);

    expect(useDefaultStoreNameRows($id))->toBe(0);
});

it('still deletes the store view value of an attribute set to false', function () {
    $id = useDefaultProduct();
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $service->save($service->getById($id, 1)->setData('name', false));

    expect(useDefaultStoreNameRows($id))->toBe(0)
        ->and($service->getById($id, 1)->getName())->toBe('Pest Default Name');
});

it('does not use the default value for a locked attribute', function () {
    $id = useDefaultProduct();
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');

    $product = $service->getById($id, 1);
    $product->lockAttribute('name')->useDefaultValue('name');

    expect($product->usesDefaultValue('name'))->toBeFalse()
        ->and($product->getName())->toBe('Pest Store Name');
});

it('saves a category in a store view with the name on the default value', function () {
    /** @var Mage_Catalog_Service_Category $service */
    $service = Mage::getService('catalog/category');
    $category = $service->newCategory((int) Mage::app()->getStore(1)->getRootCategoryId());
    $category->setName('Pest Default Name')
        ->setUrlKey('pest-use-default-' . uniqid())
        ->setIsActive(1)
        ->setIsAnchor(1)
        ->setIncludeInMenu(1);
    $id = (int) $service->save($category)->getId();

    $service->save($service->getById($id, 1)->setName('Pest Store Name'));

    $category = $service->getById($id, 1);
    $service->useDefault($category, ['name']);
    $service->save($category);

    expect($category->getName())->toBe('Pest Default Name')
        ->and($service->getById($id, 1)->getName())->toBe('Pest Default Name');
});

it('keeps a category value that a form field lists in use_default without the check', function () {
    /** @var Mage_Catalog_Service_Category $service */
    $service = Mage::getService('catalog/category');
    $category = $service->newCategory((int) Mage::app()->getStore(1)->getRootCategoryId());
    $category->setName('Pest Default Name')
        ->setUrlKey('pest-use-default-' . uniqid())
        ->setIsActive(1)
        ->setIsAnchor(1)
        ->setIncludeInMenu(1);
    $id = (int) $service->save($category)->getId();

    $service->save($service->getById($id, 1)->setName('Pest Store Name'));

    $category = $service->getById($id, 1);
    $category->addData(['use_default' => ['name']]);
    $service->useDefault($category, []);
    $service->save($category);

    expect($service->getById($id, 1)->getName())->toBe('Pest Store Name');
});

it('updates the stock data of a product in a store view with the legacy API, with the name on the default value', function () {
    $id = useDefaultProduct(storeName: false);

    $result = Mage::getModel('catalog/product_api')->update($id, ['stock_data' => ['qty' => 3, 'is_in_stock' => 1]], 1);

    expect($result)->toBeTrue()
        ->and(useDefaultStoreNameRows($id))->toBe(0)
        ->and(Mage::getService('catalog/product')->getById($id, 1)->getName())->toBe('Pest Default Name')
        ->and((float) Mage::getModel('cataloginventory/stock_item')->loadByProduct($id)->getQty())->toBe(3.0);
});
