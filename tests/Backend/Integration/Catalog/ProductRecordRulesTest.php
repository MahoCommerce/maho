<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The rules of one product are in the product model, and the flows of the admin product pages are in
 * Mage_Catalog_Service_Product (#1348): the copy between store views, the duplicate, the mass status and the save
 * of a part of a product.
 */

function productRecordService(): Mage_Catalog_Service_Product
{
    /** @var Mage_Catalog_Service_Product */
    return Mage::getService('catalog/product');
}

function productRecordCreate(string $sku): Mage_Catalog_Model_Product
{
    $service = productRecordService();
    $product = $service->newProduct()->setSku($sku)->setName('Pest Record Product')->setPrice(10);
    $service->setWebsiteIds($product, [1]);
    return $service->save($product);
}

function productRecordModel(): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getModel('catalog/product');
    return $product->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId((int) $product->getDefaultAttributeSetId())
        ->setSku('PEST-RECORD-MODEL')
        ->setName('Pest Record Product')
        ->setPrice(10)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([1]);
}

function productRecordAdmin(string $action, array $fields): array
{
    Mage::unregister('product');
    Mage::unregister('current_product');
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_ProductController::class, $action, $fields);
}

function productRecordSetSku(int $productId, string $sku): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update($resource->getTableName('catalog/product'), ['sku' => $sku], ['entity_id = ?' => $productId]);
}

function productRecordStatus(int $productId): int
{
    return (int) Mage::getResourceSingleton('catalog/product')->getAttributeRawValue($productId, 'status', 0);
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $this->productIds = [];
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    $products = Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('sku', ['like' => 'PEST-RECORD-%']);
    foreach ([...$products->getAllIds(), ...$this->productIds] as $productId) {
        Mage::getModel('catalog/product')->load($productId)->delete();
    }
    Mage::unregister('isSecureArea');
    Mage::unregister('product');
    Mage::unregister('current_product');
});

describe('the rules of the product model', function (): void {
    it('refuses a new product without a name in the default scope', function (): void {
        expect(fn() => productRecordModel()->setName(' ')->save())->toThrow(Mage_Core_Exception_Input::class, 'Name is required');
    });

    it('refuses an unknown type and an attribute set of another entity type', function (): void {
        $categorySetId = (int) Mage::getModel('catalog/category')->getDefaultAttributeSetId();

        expect(fn() => productRecordModel()->setTypeId('pest_unknown')->save())->toThrow(Mage_Core_Exception_Input::class, 'Unknown product type: pest_unknown')
            ->and(fn() => productRecordModel()->setAttributeSetId($categorySetId)->save())->toThrow(Mage_Core_Exception_Input::class, "Attribute set {$categorySetId} not found");
    });

    it('saves a store view that has no name of its own', function (): void {
        $product = productRecordCreate('PEST-RECORD-STORE');

        $inStore = Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
        $inStore->setName(false)->setDescription('Pest store description')->save();

        expect(Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId())->getName())->toBe('Pest Record Product');
    });

    it('saves a product without a SKU, as the duplicate of a product is', function (): void {
        $product = productRecordModel()->setSku(null);
        $product->save();
        $this->productIds[] = (int) $product->getId();

        expect($product->getId())->not->toBeNull()
            ->and(fn() => productRecordService()->save(Mage::getModel('catalog/product')->load($product->getId())))
            ->toThrow(Mage_Core_Exception_Input::class, 'SKU is required');
    });

    it('saves a counter of a product that holds an old empty name', function (): void {
        $product = productRecordCreate('PEST-RECORD-OLD');
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name');
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->delete($attribute->getBackendTable(), [
            'attribute_id = ?' => (int) $attribute->getId(),
            'entity_id = ?' => (int) $product->getId(),
        ]);

        $stored = Mage::getModel('catalog/product')->load($product->getId());
        $stored->setPrice(12)->save();

        expect((float) Mage::getModel('catalog/product')->load($product->getId())->getPrice())->toBe(12.0)
            ->and(fn() => productRecordService()->save(Mage::getModel('catalog/product')->load($product->getId())))
            ->toThrow(Mage_Core_Exception_Input::class, 'Name is required');
    });

    it('tells whether the product is in one of the websites', function (): void {
        $product = productRecordModel();

        expect($product->isInWebsites([1, 99]))->toBeTrue()
            ->and($product->isInWebsites(['1']))->toBeTrue()
            ->and($product->isInWebsites([99]))->toBeFalse()
            ->and(Mage::getResourceSingleton('catalog/product')->getIdsNotInWebsites([999999991], [1]))->toBe([999999991]);
    });

    it('gives the attribute exception of the SKU to code that validates the model', function (): void {
        productRecordCreate('PEST-RECORD-TAKEN');
        $product = productRecordModel()->setSku('PEST-RECORD-TAKEN');

        try {
            $product->validate();
            $caught = null;
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $caught = $e;
        }

        expect($caught)->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Exception::class)
            ->and($caught?->getAttributeCode())->toBe('sku')
            ->and(fn() => productRecordService()->validate($product))->toThrow(Mage_Core_Exception_Conflict::class, 'The value of attribute "SKU" must be unique');
    });
});

describe('the product service', function (): void {
    it('gives a new product its defaults', function (): void {
        $product = productRecordService()->newProduct();

        expect((int) $product->getStatus())->toBe(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->and((int) $product->getVisibility())->toBe(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->and((int) $product->getTaxClassId())->toBe(0);
    });

    it('names no query string in the error of a default value in the default scope', function (): void {
        $product = productRecordCreate('PEST-RECORD-DEFAULT');

        expect(fn() => productRecordService()->useDefault($product, ['name']))
            ->toThrow(Mage_Core_Exception_Input::class, 'Only a store view can use the default value: the default scope holds the default value.');
    });

    it('returns only the values of the attributes that a mass change writes', function (): void {
        expect(productRecordService()->validateAttributes(['name' => 'Pest', 'pest_unknown_attribute' => 1, 'sku' => 'X']))->toBe(['name' => 'Pest']);
    });

    it('copies the values of a store view to another store view, in the service and on the admin page', function (): void {
        $product = productRecordCreate('PEST-RECORD-COPY');
        $productId = (int) $product->getId();
        $target = (int) array_values(array_diff(array_keys(Mage::app()->getStores()), [1]))[0];
        $storeName = fn(int $storeId): string => (string) Mage::getResourceSingleton('catalog/product')->getAttributeRawValue($productId, 'name', $storeId);
        $setNames = function () use ($productId, $target): void {
            Mage::getSingleton('catalog/product_action')->updateAttributes([$productId], ['name' => 'Pest source name'], 1);
            Mage::getSingleton('catalog/product_action')->updateAttributes([$productId], ['name' => 'Pest target name'], $target);
        };

        $setNames();
        productRecordService()->copyToStores($product, [$target => 1]);
        expect($storeName($target))->toBe('Pest source name');

        $setNames();
        $admin = productRecordAdmin('save', ['id' => $productId, 'product' => ['website_ids' => [1]], 'copy_to_stores' => [$target => 1]]);
        expect($admin['errors'])->toBe([])
            ->and($storeName($target))->toBe('Pest source name');
    });

    it('keeps the values of a store view in a copy from the default values', function (): void {
        $product = productRecordCreate('PEST-RECORD-COPY-DEFAULT');
        $productId = (int) $product->getId();

        Mage::getSingleton('catalog/product_action')->updateAttributes([$productId], ['name' => 'Pest store name'], 1);
        productRecordService()->copyToStores($product, [1 => 0]);

        expect((string) Mage::getResourceSingleton('catalog/product')->getAttributeRawValue($productId, 'name', 1))->toBe('Pest store name');
    });

    it('duplicates a product, in the service and on the admin page', function (): void {
        $product = productRecordCreate('PEST-RECORD-SOURCE');
        $before = Mage::getModel('catalog/product')->getCollection()->getAllIds();

        $copy = productRecordService()->duplicate($product, false);
        $admin = productRecordAdmin('duplicate', ['id' => (int) $product->getId()]);

        $newIds = array_values(array_diff(Mage::getModel('catalog/product')->getCollection()->getAllIds(), $before));
        $this->productIds = array_map(intval(...), $newIds);
        expect($admin['errors'])->toBe([])
            ->and($newIds)->toHaveCount(2)
            ->and((string) $copy->getSku())->toBe('')
            ->and((int) $copy->getStatus())->toBe(Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
    });

    it('refuses a part of a product without a SKU, and saves a part of a product with an old empty value', function (): void {
        $product = productRecordCreate('PEST-RECORD-PART');
        $productId = (int) $product->getId();
        $rows = [['website_id' => 0, 'cust_group' => Mage_Customer_Model_Group::CUST_GROUP_ALL, 'price_qty' => 2.0, 'price' => 8.0]];
        /** @var Mage_Catalog_Service_Product_Price $prices */
        $prices = Mage::getService('catalog/product_price');

        productRecordSetSku($productId, '');
        try {
            expect(fn() => $prices->setTierPrices($product, $rows))->toThrow(Mage_Core_Exception_Input::class, 'SKU is required');
        } finally {
            productRecordSetSku($productId, 'PEST-RECORD-PART');
        }

        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name');
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete($attribute->getBackendTable(), [
            'attribute_id = ?' => (int) $attribute->getId(),
            'entity_id = ?' => $productId,
        ]);
        $prices->setTierPrices($product, $rows);

        expect(Mage::getModel('catalog/product')->load($productId)->getTierPrice())->toHaveCount(1);
    });
});

describe('the mass status', function (): void {
    it('disables a product without a SKU and does not enable it, in the service and on the admin page', function (): void {
        $product = productRecordCreate('PEST-RECORD-STATUS');
        $productId = (int) $product->getId();
        $enabled = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
        $disabled = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        $message = 'Some of the processed products have no SKU value defined. Please fill it prior to performing operations on these products.';

        productRecordSetSku($productId, '');
        try {
            $admin = productRecordAdmin('massStatus', ['product' => [$productId], 'status' => $disabled]);
            expect($admin['errors'])->toBe([])
                ->and(productRecordStatus($productId))->toBe($disabled);

            $admin = productRecordAdmin('massStatus', ['product' => [$productId], 'status' => $enabled]);
            expect($admin['errors'])->toBe([$message])
                ->and(fn() => productRecordService()->updateStatus([$productId], $enabled, 0))->toThrow(Mage_Core_Exception::class, $message)
                ->and(productRecordStatus($productId))->toBe($disabled);

            productRecordService()->updateStatus([$productId], $disabled, 0);
        } finally {
            productRecordSetSku($productId, 'PEST-RECORD-STATUS');
        }
    });
});
