<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the decimals of the default value of a decimal attribute', function () {
    $attribute = Mage::getModel('eav/entity_attribute')
        ->setBackendType('decimal')
        ->setDefaultValue('1.5');

    (new ReflectionMethod($attribute, '_beforeSave'))->invoke($attribute);

    expect($attribute->getDefaultValue())->toBe('1.5');
});

it('rejects a default value of a decimal attribute that is not a number', function () {
    $attribute = Mage::getModel('eav/entity_attribute')
        ->setBackendType('decimal')
        ->setDefaultValue('abc');

    (new ReflectionMethod($attribute, '_beforeSave'))->invoke($attribute);
})->throws(Mage_Eav_Exception::class);
