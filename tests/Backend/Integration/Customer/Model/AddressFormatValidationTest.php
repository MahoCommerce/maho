<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function addressFormatAddress(string $modelClass, array $data): Mage_Customer_Model_Address_Abstract
{
    return Mage::getModel($modelClass)->setData($data + [
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'street' => '1 Main St',
        'city' => 'Rome',
        'telephone' => '0000000000',
    ]);
}

it('rejects a postcode that does not match the format of the country', function () {
    $errors = addressFormatAddress('customer/address', ['country_id' => 'IT', 'postcode' => '0014'])->validate();

    expect($errors)->toBeArray()
        ->and(implode(' ', $errors))->toContain('(example: 00144)');
});

it('accepts a postcode that matches the format of the country', function () {
    expect(addressFormatAddress('customer/address', ['country_id' => 'IT', 'postcode' => '00144'])->validate())
        ->toBeTrue();
});

it('accepts an empty postcode for a country with an optional postcode', function () {
    expect(addressFormatAddress('customer/address', ['country_id' => 'IE', 'postcode' => ''])->validate())
        ->toBeTrue();
});

it('rejects a region of another country', function () {
    $californiaId = (int) Mage::getModel('directory/region')->loadByCode('CA', 'US')->getId();
    expect($californiaId)->toBeGreaterThan(0);

    $address = addressFormatAddress('customer/address', [
        'country_id' => 'CA',
        'postcode' => 'H3Z 2Y7',
        'region_id' => $californiaId,
    ]);

    expect($address->getFormatErrors())
        ->toBe(['The selected state/province is not valid for the chosen country.']);
});

it('accepts a region of the country', function () {
    $ontarioId = (int) Mage::getModel('directory/region')->loadByCode('ON', 'CA')->getId();

    $address = addressFormatAddress('customer/address', [
        'country_id' => 'CA',
        'postcode' => 'H3Z 2Y7',
        'region_id' => $ontarioId,
    ]);

    expect($address->getFormatErrors())->toBe([]);
});

it('reports a wrong postcode on an order address, as the admin order address edit does', function () {
    $address = addressFormatAddress('sales/order_address', ['country_id' => 'US', 'postcode' => '9501']);

    expect($address->getFormatErrors())->toHaveCount(1)
        ->and($address->getFormatErrors()[0])->toContain('(example: 95014)');
});

it('rejects a wrong postcode in the address form, as the admin customer save does', function () {
    $form = Mage::getModel('customer/form')
        ->setFormCode('adminhtml_customer_address')
        ->setEntity(Mage::getModel('customer/address'));

    $errors = $form->validateData([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'street' => ['1 Main St'],
        'city' => 'Rome',
        'country_id' => 'IT',
        'postcode' => '0014',
        'telephone' => '0000000000',
    ]);

    expect($errors)->toBeArray()
        ->and(implode(' ', $errors))->toContain('(example: 00144)');
});

it('puts the country first in the admin address forms', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()
        ->from(['ea' => $resource->getTableName('eav/attribute')], ['attribute_code'])
        ->join(['cea' => $resource->getTableName('customer/eav_attribute')], 'cea.attribute_id = ea.attribute_id', ['sort_order'])
        ->where('ea.entity_type_id = ?', (int) Mage::getSingleton('eav/config')->getEntityType('customer_address')->getId())
        ->where('ea.attribute_code IN (?)', ['country_id', 'region_id', 'postcode', 'city', 'street']);
    $sortOrders = array_map('intval', $adapter->fetchPairs($select));

    expect($sortOrders['country_id'])->toBeLessThan($sortOrders['region_id'])
        ->and($sortOrders['region_id'])->toBeLessThan($sortOrders['postcode'])
        ->and($sortOrders['postcode'])->toBeLessThan($sortOrders['city'])
        ->and($sortOrders['city'])->toBeLessThan($sortOrders['street']);
});
