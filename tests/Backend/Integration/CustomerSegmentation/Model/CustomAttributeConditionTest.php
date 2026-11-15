<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function segmentCustomAttribute(string $entityTypeCode, string $code, string $input, array $options = []): Mage_Customer_Model_Attribute
{
    $data = [
        'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType($entityTypeCode)->getId(),
        'attribute_code' => $code,
        'frontend_input' => $input,
        'frontend_label' => 'Pest ' . $code,
        'backend_type' => in_array($input, ['select', 'boolean'], true) ? 'int' : 'varchar',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => [$entityTypeCode === 'customer' ? 'adminhtml_customer' : 'adminhtml_customer_address'],
    ];
    if ($options) {
        $data['source_model'] = 'eav/entity_attribute_source_table';
        $data['option'] = ['value' => [], 'order' => []];
        foreach ($options as $i => $label) {
            $data['option']['value']['o' . $i] = [$label];
            $data['option']['order']['o' . $i] = $i;
        }
    }
    if ($input === 'multiselect') {
        $data['backend_model'] = 'eav/entity_attribute_backend_array';
    }
    $attribute = Mage::getModel('customer/attribute')->setData($data);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

function segmentCustomOptionId(string $code, string $label): string
{
    $options = Mage::getModel('customer/attribute')->loadByCode('customer', $code)->getSource()->getAllOptions();
    return (string) array_column($options, 'value', 'label')[$label];
}

/**
 * Return whether $customerId matches a condition of $class on $attribute.
 */
function segmentCustomMatches(string $class, string $attribute, string $operator, string $value, int $customerId): bool
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $sql = Mage::getModel($class)->setAttribute($attribute)->setOperator($operator)->setValue($value)->getConditionsSql($adapter);
    $ids = $adapter->fetchCol(
        $adapter->select()
            ->from(['e' => Mage::getSingleton('core/resource')->getTableName('customer/entity')], ['entity_id'])
            ->where($sql),
    );
    return in_array($customerId, array_map(intval(...), $ids), true);
}

beforeEach(function () {
    $this->suffix = substr(uniqid(), -6);
    $this->type = 'pest_type_' . $this->suffix;
    $this->interests = 'pest_int_' . $this->suffix;
    $this->dock = 'pest_dock_' . $this->suffix;
    $this->attributes = [
        segmentCustomAttribute('customer', $this->type, 'select', ['Retailer', 'Wholesaler']),
        segmentCustomAttribute('customer', $this->interests, 'multiselect', ['Rings', 'Watches', 'Bags']),
        segmentCustomAttribute('customer_address', $this->dock, 'boolean'),
    ];
    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getWebsite(true)->getId())
        ->setEmail('pest-segment-' . $this->suffix . '@example.com')
        ->setFirstname('Pest')
        ->setLastname('Segment')
        ->setData($this->type, segmentCustomOptionId($this->type, 'Wholesaler'))
        ->setData($this->interests, segmentCustomOptionId($this->interests, 'Rings') . ',' . segmentCustomOptionId($this->interests, 'Bags'));
    $this->customer->save();
    Mage::getModel('customer/address')
        ->setCustomerId((int) $this->customer->getId())
        ->setFirstname('Pest')
        ->setLastname('Segment')
        ->setStreet(['Via Roma 1'])
        ->setCity('Milano')
        ->setCountryId('IT')
        ->setPostcode('20100')
        ->setTelephone('0200000000')
        ->setData($this->dock, 1)
        ->save();
});

afterEach(function () {
    $this->customer->delete();
    foreach ($this->attributes as $attribute) {
        $attribute->delete();
    }
    Mage::getSingleton('eav/config')->clear();
});

it('offers the user-defined customer attributes with the control of their input type', function () {
    $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
    $condition->loadAttributeOptions();
    $condition->setAttribute($this->type);

    expect($condition->getAttributeOption())->toHaveKey($this->type)
        ->and($condition->getAttributeOption())->toHaveKey($this->interests)
        ->and($condition->getValueElementType())->toBe('select')
        ->and(array_column($condition->getOperatorSelectOptions(), 'value'))->toBe(['==', '!='])
        ->and(array_column($condition->getValueSelectOptions(), 'label'))->toBe(['Retailer', 'Wholesaler']);
});

it('matches a dropdown customer attribute', function () {
    $class = 'customersegmentation/segment_condition_customer_attributes';
    $id = (int) $this->customer->getId();

    expect(segmentCustomMatches($class, $this->type, '==', segmentCustomOptionId($this->type, 'Wholesaler'), $id))->toBeTrue()
        ->and(segmentCustomMatches($class, $this->type, '==', segmentCustomOptionId($this->type, 'Retailer'), $id))->toBeFalse()
        ->and(segmentCustomMatches($class, $this->type, '!=', segmentCustomOptionId($this->type, 'Retailer'), $id))->toBeTrue();
});

it('matches a multiple select customer attribute that contains an option', function () {
    $class = 'customersegmentation/segment_condition_customer_attributes';
    $id = (int) $this->customer->getId();

    expect(segmentCustomMatches($class, $this->interests, '{}', segmentCustomOptionId($this->interests, 'Bags'), $id))->toBeTrue()
        ->and(segmentCustomMatches($class, $this->interests, '{}', segmentCustomOptionId($this->interests, 'Watches'), $id))->toBeFalse()
        ->and(segmentCustomMatches($class, $this->interests, '!{}', segmentCustomOptionId($this->interests, 'Watches'), $id))->toBeTrue()
        ->and(segmentCustomMatches($class, $this->interests, '!{}', segmentCustomOptionId($this->interests, 'Rings'), $id))->toBeFalse();
});

it('matches a Yes/No address attribute', function () {
    $class = 'customersegmentation/segment_condition_customer_address';
    $id = (int) $this->customer->getId();

    expect(segmentCustomMatches($class, $this->dock, '==', '1', $id))->toBeTrue()
        ->and(segmentCustomMatches($class, $this->dock, '==', '0', $id))->toBeFalse();
});
