<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The quick create of a simple product adds the "simple_product" form fields to the product. A form field must not
 * fill the "Use Default Value" list: with the entity_id of a stored product, the save would delete its values.
 */

function quickCreateUseDefaultProduct(string $sku, string $name, string $typeId): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setTypeId($typeId)
        ->setAttributeSetId(4)
        ->setSku($sku . '-' . uniqid())
        ->setName($name)
        ->setPrice(10.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->save();

    return $product;
}

function quickCreateUseDefaultPost(array $params): void
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/catalog_product/quickCreate', 'POST', $params),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName('catalog_product')
        ->setActionName('quickCreate')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Adminhtml_Catalog_ProductController($request, new Mage_Core_Controller_Response_Http());

    try {
        $controller->quickCreateAction();
    } catch (Throwable) {
        // The save runs before the response is built. The assertions check the saved data.
    }
}

beforeEach(function () {
    $this->user = AdminRoleFixture::login(['admin/catalog', 'admin/catalog/products']);
    $this->products = [];
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach (array_reverse($this->products) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
    AdminRoleFixture::delete($this->user);
});

it('does not let a form field delete a value of a stored product', function () {
    $this->products[] = $configurable = quickCreateUseDefaultProduct('quick-create-configurable', 'Pest Configurable', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
    $this->products[] = $stored = quickCreateUseDefaultProduct('quick-create-stored', 'Pest Default Name', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);

    quickCreateUseDefaultPost([
        'product' => $configurable->getId(),
        'simple_product' => [
            'entity_id' => $stored->getId(),
            'sku' => $stored->getSku(),
            'name' => 'Pest Default Name',
            'price' => 12,
            'use_default' => ['name'],
        ],
    ]);

    $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->load($stored->getId());

    expect((float) $product->getPrice())->toBe(12.0)
        ->and($product->getName())->toBe('Pest Default Name');
});
