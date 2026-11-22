<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function newCustomerAttribute(): Mage_Eav_Model_Entity_Attribute
{
    return Mage::getModel('eav/entity_attribute')
        ->setEntityTypeId((int) Mage::getSingleton('eav/config')->getEntityType('customer')->getId())
        ->setAttributeCode('pest_attribute');
}

it('gives the entity resource to an attribute that no entity model loaded', function () {
    $attribute = newCustomerAttribute();

    expect($attribute->getEntity())->toBeInstanceOf(Mage_Customer_Model_Resource_Customer::class)
        ->and($attribute->getEntityIdField())->toBe('entity_id');
});

it('refuses a frontend model that is not an attribute frontend', function () {
    $attribute = newCustomerAttribute()->setFrontendModel('core/store');

    expect(fn() => $attribute->getFrontend())
        ->toThrow(RuntimeException::class, 'Model core/store is not an attribute frontend model.');
});

it('refuses a backend model that is not an attribute backend', function () {
    $attribute = newCustomerAttribute()->setBackendModel('core/store');

    expect(fn() => $attribute->getBackend())
        ->toThrow(Mage_Eav_Exception::class, 'Invalid backend model specified: core/store');
});

it('refuses a source model that is not an attribute source', function () {
    $attribute = newCustomerAttribute()->setSourceModel('core/store');

    expect(fn() => $attribute->getSource())
        ->toThrow(Mage_Eav_Exception::class, 'Source model "core/store" not found for attribute "pest_attribute"');
});
