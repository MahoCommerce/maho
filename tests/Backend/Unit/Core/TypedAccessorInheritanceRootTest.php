<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Wave 4 converts the accessors on the roots of the inheritance chains:
 * Mage_Core_Model_Abstract, Mage_Eav_Model_Entity_Attribute_Abstract,
 * Mage_Rule_Model_Condition_Abstract, Mage_Payment_Model_Method_Abstract,
 * Mage_Shipping_Model_Carrier_Abstract and Mage_Payment_Model_Info.
 * Database-shaped input must come back typed, and an unset key must stay null.
 */

it('casts the created_at column on any core model', function () {
    $model = Mage::getModel('core/email_template');
    $model->setData('created_at', '2026-01-02 03:04:05');

    expect($model->getCreatedAt())->toBe('2026-01-02 03:04:05')
        ->and(Mage::getModel('core/email_template')->getCreatedAt())->toBeNull();
});

it('reads the address keys on a core model', function () {
    $address = Mage::getModel('customer/address');
    $model = Mage::getModel('core/email_template');

    expect($model->getBillingAddress())->toBeNull()
        ->and($model->getShippingAddress())->toBeNull();

    $model->setData('billing_address', $address);
    $model->setData('shipping_address', $address);

    expect($model->getBillingAddress())->toBe($address)
        ->and($model->getShippingAddress())->toBe($address);
});

it('returns null instead of false when an order has no billing address', function () {
    expect(Mage::getModel('sales/order')->getBillingAddress())->toBeNull()
        ->and(Mage::getModel('sales/order')->getShippingAddress())->toBeNull();
});

it('types the eav attribute columns', function () {
    /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
    $attribute = Mage::getModel('catalog/resource_eav_attribute');
    $attribute->setData('is_filterable', '2');
    $attribute->setData('is_required', '1');
    $attribute->setData('is_searchable', '0');
    $attribute->setData('used_for_sort_by', '1');
    $attribute->setData('sort_order', '30');
    $attribute->setData('store_id', '2');
    $attribute->setData('frontend_label', 'Color');

    expect($attribute->getIsFilterable())->toBe(2)
        ->and($attribute->getIsRequired())->toBeTrue()
        ->and($attribute->getIsSearchable())->toBeFalse()
        ->and($attribute->getUsedForSortBy())->toBeTrue()
        ->and($attribute->getSortOrder())->toBe(30)
        ->and($attribute->getStoreId())->toBe(2)
        ->and($attribute->getFrontendLabel())->toBe('Color');
});

it('returns null for eav attribute keys the model does not hold', function () {
    $attribute = Mage::getModel('catalog/resource_eav_attribute');

    expect($attribute->getIsFilterable())->toBeNull()
        ->and($attribute->getSortOrder())->toBeNull()
        ->and($attribute->getAttributeSetInfo())->toBeNull();
});

it('types the rule condition keys', function () {
    /** @var Mage_Rule_Model_Condition_Abstract $condition */
    $condition = Mage::getModel('salesrule/rule_condition_address');
    $condition->setData('is_value_parsed', '1');
    $condition->setData('explicit_apply', '0');

    expect($condition->getIsValueParsed())->toBeTrue()
        ->and($condition->getExplicitApply())->toBeFalse()
        ->and(Mage::getModel('salesrule/rule_condition_address')->getIsValueParsed())->toBeNull();
});

it('reads the condition option maps without an index argument', function () {
    /** @var Mage_Rule_Model_Condition_Abstract $condition */
    $condition = Mage::getModel('salesrule/rule_condition_address');
    $condition->setAttributeOption(['base_subtotal' => 'Subtotal']);
    $condition->setAttribute('base_subtotal');
    $condition->setOperator('==');

    expect($condition->getAttributeName())->toBe('Subtotal')
        ->and($condition->getOperatorName())->toBe('is');
});

it('keeps the payment method store key polymorphic', function () {
    /** @var Mage_Payment_Model_Method_Abstract $method */
    $method = Mage::getModel('payment/method_checkmo');

    expect($method->getStore())->toBeNull();

    $method->setStore(1);
    expect($method->getStore())->toBe(1);

    $store = Mage::app()->getStore();
    $method->setStore($store);
    expect($method->getStore())->toBe($store);
});

it('types the carrier keys', function () {
    /** @var Mage_Shipping_Model_Carrier_Abstract $carrier */
    $carrier = Mage::getModel('shipping/carrier_flatrate');

    expect($carrier->getContainerTypesAll())->toBeNull()
        ->and($carrier->getContainerTypesFilter())->toBeNull()
        ->and($carrier->getStore())->toBeNull();

    $carrier->setActiveFlag('active');
    $carrier->setStore(1);

    expect($carrier->getData('active_flag'))->toBe('active')
        ->and($carrier->getStore())->toBe(1);
});

it('types the payment info card keys', function () {
    $payment = Mage::getModel('sales/quote_payment');
    $payment->setData('cc_exp_month', 12);
    $payment->setData('cc_exp_year', 2030);
    $payment->setData('cc_last4', 1111);

    expect($payment->getCcExpMonth())->toBe('12')
        ->and($payment->getCcExpYear())->toBe('2030')
        ->and($payment->getCcLast4())->toBe('1111')
        ->and($payment->getCcType())->toBeNull()
        ->and($payment->getMethod())->toBeNull();
});
