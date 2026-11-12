<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The rules of the attribute form are in Mage_Eav_Service_Attribute (#1348). A setup script that saves the
 * attribute model works as before: only the length of the code and the reserved codes of products refuse a save.
 */

function attributeModelNew(string $code): Mage_Catalog_Model_Resource_Eav_Attribute
{
    /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
    $attribute = Mage::getService('eav/attribute')->newAttribute(Mage_Catalog_Model_Product::ENTITY);
    return $attribute->addData([
        'attribute_code' => $code,
        'frontend_input' => 'text',
        'backend_type' => 'varchar',
        'is_user_defined' => 1,
    ]);
}

function attributeModelCleanup(): void
{
    $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->addFieldToFilter('attribute_code', ['like' => 'Pest_Model_%']);
    foreach ($attributes as $attribute) {
        $attribute->delete();
    }
}

beforeEach(function (): void {
    attributeModelCleanup();
});

afterEach(function (): void {
    attributeModelCleanup();
});

it('saves a code that the form refuses, without a label, in a direct save of the model', function (): void {
    $attribute = attributeModelNew('Pest_Model_UPPER');

    expect($attribute->getValidationErrors()->getErrors())->not->toBe([]);

    $attribute->save();

    expect($attribute->getId())->not->toBeNull();
});

it('refuses the same attribute in the service', function (): void {
    $attribute = attributeModelNew('Pest_Model_UPPER');

    expect(fn() => Mage::getService('eav/attribute')->save($attribute))->toThrow(Mage_Core_Exception_Input::class);
});

it('refuses a code that is too long in a direct save of the model', function (): void {
    $attribute = attributeModelNew('Pest_Model_' . str_repeat('a', Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_MAX_LENGTH));

    expect(fn() => $attribute->save())->toThrow(Mage_Eav_Exception::class);
});

it('refuses a code that the product model reserves in a direct save of the model', function (): void {
    $attribute = attributeModelNew('type_instance');

    expect(fn() => $attribute->save())->toThrow(Mage_Core_Exception::class, "The attribute code 'type_instance' is reserved by system.");
});

it('keeps the deprecated observer of the reserved codes', function (): void {
    $observer = new Maho\Event\Observer();
    $observer->setEvent(new Maho\Event(['attribute' => attributeModelNew('type_instance')]));

    expect(fn() => Mage::getModel('catalog/observer')->checkReservedAttributeCodes($observer))->toThrow(Mage_Core_Exception::class);
});
