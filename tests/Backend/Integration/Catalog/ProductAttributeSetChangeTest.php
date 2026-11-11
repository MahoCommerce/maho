<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Catalog_Service_Product::changeAttributeSet() moves products to another attribute set without a save of
 * each product, for the mass action of the admin product grid (#1348).
 */

function attributeSetChangeProduct(string $typeId = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct($typeId)
        ->setSku('PEST-SET-CHANGE-' . uniqid())
        ->setName('Pest Set Change')
        ->setPrice(10)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0);
    $service->setWebsiteIds($product, [1]);
    return $service->save($product);
}

/**
 * Return a new product attribute set. With $skeletonId, it has the attributes of that set. Without, it has none.
 */
function attributeSetChangeSet(?int $skeletonId): Mage_Eav_Model_Entity_Attribute_Set
{
    /** @var Mage_Eav_Model_Entity_Attribute_Set $set */
    $set = Mage::getModel('eav/entity_attribute_set');
    $set->setEntityTypeId((int) Mage::getResourceSingleton('catalog/product')->getTypeId())
        ->setAttributeSetName('Pest set change ' . uniqid())
        ->save();
    if ($skeletonId !== null) {
        $set->initFromSkeleton($skeletonId)->save();
    }
    return $set;
}

function attributeSetChangeService(): Mage_Catalog_Service_Product
{
    /** @var Mage_Catalog_Service_Product */
    return Mage::getService('catalog/product');
}

beforeEach(function (): void {
    $this->products = [];
    $this->sets = [];
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    foreach (array_reverse($this->products) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
    foreach ($this->sets as $set) {
        $set->delete();
    }
});

it('moves the products to the set and changes updated_at', function (): void {
    $this->products[] = $product = attributeSetChangeProduct();
    $this->sets[] = $set = attributeSetChangeSet((int) $product->getAttributeSetId());
    $productId = (int) $product->getId();
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update($resource->getTableName('catalog/product'), ['updated_at' => '2020-01-01 00:00:00'], ['entity_id = ?' => $productId]);

    $count = attributeSetChangeService()->changeAttributeSet([(string) $productId], (int) $set->getId());

    $row = $resource->getConnection('core_read')->fetchRow(
        $resource->getConnection('core_read')->select()
            ->from($resource->getTableName('catalog/product'), ['attribute_set_id', 'updated_at'])
            ->where('entity_id = ?', $productId),
    );
    expect($count)->toBe(1)
        ->and((int) $row['attribute_set_id'])->toBe((int) $set->getId())
        ->and($row['updated_at'])->not->toStartWith('2020-01-01');
});

it('refuses a set that does not exist and a set of another entity type', function (int|string $setId): void {
    $this->products[] = $product = attributeSetChangeProduct();
    $before = (int) $product->getAttributeSetId();
    $setId = $setId === 'category' ? (int) Mage::getModel('catalog/category')->getDefaultAttributeSetId() : $setId;

    expect(fn() => attributeSetChangeService()->changeAttributeSet([(int) $product->getId()], $setId))
        ->toThrow(Mage_Core_Exception_Input::class, "Attribute set {$setId} not found")
        ->and((int) Mage::getModel('catalog/product')->load($product->getId())->getAttributeSetId())->toBe($before);
})->with([
    'an unknown set' => [999999],
    'a category set' => ['category'],
]);

it('refuses a set without the super attribute of a configurable product', function (): void {
    $this->products[] = $configurable = attributeSetChangeProduct(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
    /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
    $typeInstance = $configurable->getTypeInstance(true);
    $code = null;
    foreach ($typeInstance->getSetAttributes($configurable) as $attribute) {
        if ($typeInstance->canUseAttribute($attribute)) {
            $code = $attribute->getAttributeCode();
            break;
        }
    }
    if ($code === null) {
        $this->markTestSkipped('The default attribute set has no attribute for a configurable product');
    }
    Mage::getService('catalog/product_configurable')->setSuperAttributes($configurable, [$code]);
    $this->sets[] = $emptySet = attributeSetChangeSet(null);
    $this->sets[] = $fullSet = attributeSetChangeSet((int) $configurable->getAttributeSetId());
    $before = (int) $configurable->getAttributeSetId();
    $productId = (int) $configurable->getId();

    expect(fn() => attributeSetChangeService()->changeAttributeSet([$productId], (int) $emptySet->getId()))
        ->toThrow(Mage_Core_Exception_Conflict::class, "The attribute set does not have the attribute '{$code}' that the configurable product {$configurable->getSku()} uses.")
        ->and((int) Mage::getModel('catalog/product')->load($productId)->getAttributeSetId())->toBe($before)
        ->and(attributeSetChangeService()->changeAttributeSet([$productId], (int) $fullSet->getId()))->toBe(1);
});
