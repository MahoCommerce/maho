<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * "Use Default Value" sets an attribute to false, and the save deletes the store view value (#1857). A typed getter
 * returns null for false, so the observers of the save never get false.
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

it('saves a product in a store view with the name on the default value', function () {
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

    $service->save($service->getById($id, 1)->setName('Pest Store Name'));

    $product = $service->getById($id, 1);
    $product->setStockData(['qty' => 5, 'is_in_stock' => 1]);
    $service->useDefault($product, ['name']);
    $service->save($product);

    expect($product->getName())->toBeNull()
        ->and($product->getStockItem()->getProductName())->toBeNull()
        ->and($service->getById($id, 1)->getName())->toBe('Pest Default Name');
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

    expect($category->getName())->toBeNull()
        ->and($service->getById($id, 1)->getName())->toBe('Pest Default Name');
});

it('returns null from a typed getter of an attribute on the default value', function () {
    $product = Mage::getModel('catalog/product')->setData(['tax_class_id' => false, 'url_key' => false, 'description' => false]);

    expect($product->getTaxClassId())->toBeNull()
        ->and($product->getUrlKey())->toBeNull()
        ->and($product->getDescription())->toBeNull();
});
