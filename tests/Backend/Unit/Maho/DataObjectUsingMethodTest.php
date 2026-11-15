<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

it('keeps the key of a code with a digit after an underscore', function () {
    $object = new DataObject();
    $object->setDataUsingMethod('floor_2nd', 'upper');

    expect($object->getData())->toBe(['floor_2nd' => 'upper'])
        ->and($object->getDataUsingMethod('floor_2nd'))->toBe('upper');
});

it('still calls a real setter and getter', function () {
    $object = new class extends DataObject {
        public function setFloor(string $value): static
        {
            return $this->setData('floor', strtoupper($value));
        }

        public function getFloor(): string
        {
            return 'floor ' . $this->getData('floor');
        }
    };
    $object->setDataUsingMethod('floor', 'second');

    expect($object->getData('floor'))->toBe('SECOND')
        ->and($object->getDataUsingMethod('floor'))->toBe('floor SECOND');
});

it('stores a Text attribute value under its own code', function () {
    $attribute = Mage::getModel('customer/attribute')
        ->setAttributeCode('floor_2nd')
        ->setFrontendInput('text');
    $address = Mage::getModel('customer/address');
    Mage_Eav_Model_Attribute_Data::factory($attribute, $address)->compactValue('upper');

    expect($address->getData('floor_2nd'))->toBe('upper')
        ->and($address->hasData('floor2nd'))->toBeFalse();
});

it('stores a camelCase key under its underscore key, as the magic setter does', function () {
    $object = new DataObject();
    $object->setDataUsingMethod('myValue', 'x');

    expect($object->getData('my_value'))->toBe('x')
        ->and($object->getDataUsingMethod('myValue'))->toBe('x');
});
