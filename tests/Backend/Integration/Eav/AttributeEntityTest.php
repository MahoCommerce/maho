<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('gives the entity resource to an attribute that no entity model loaded', function () {
    $attribute = Mage::getModel('eav/entity_attribute')
        ->setEntityTypeId((int) Mage::getSingleton('eav/config')->getEntityType('customer')->getId())
        ->setAttributeCode('pest_attribute');

    expect($attribute->getEntity())->toBeInstanceOf(Mage_Customer_Model_Resource_Customer::class)
        ->and($attribute->getEntityIdField())->toBe('entity_id');
});
