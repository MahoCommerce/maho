<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the multiselect option 0 and drops the empty value of the form', function () {
    $attribute = Mage::getModel('eav/entity_attribute')->setAttributeCode('colors');
    $backend = Mage::getModel('eav/entity_attribute_backend_array')->setAttribute($attribute);
    $object = new \Maho\DataObject(['colors' => ['', '0', 3, null]]);

    $backend->beforeSave($object);

    expect($object->getData('colors'))->toBe('0,3');
});
