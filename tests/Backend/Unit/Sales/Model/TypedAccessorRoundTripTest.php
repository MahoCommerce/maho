<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Database-shaped input (decimal and integer columns arrive as strings on
 * MySQL and PostgreSQL) must come back typed from every wave 3 model, and an
 * unset key must stay null.
 */

dataset('typed accessor round trips', [
    'order' => ['sales/order', 'grand_total', '25.5000', 25.5, 'customer_id', '42', 42],
    'order gender' => ['sales/order', 'customer_gender', '1', 1, 'customer_group_id', '3', 3],
    'order flag' => ['sales/order', 'is_virtual', '1', true, 'email_sent', '0', false],
    'quote' => ['sales/quote', 'items_qty', '3.0000', 3.0, 'customer_is_guest', '0', false],
    'quote gender' => ['sales/quote', 'customer_gender', '2', 2, 'store_id', '1', 1],
    'quote address' => ['sales/quote_address', 'subtotal', '99.9900', 99.99, 'region_id', '12', 12],
    'quote item' => ['sales/quote_item', 'row_total', '10.0000', 10.0, 'product_id', '7', 7],
    'quote item weee' => ['sales/quote_item', 'weee_tax_disposition', '0.5000', 0.5, 'store_id', '2', 2],
    'quote address item' => ['sales/quote_address_item', 'row_total', '10.0000', 10.0, 'quote_item_id', '9', 9],
    'order address' => ['sales/order_address', 'region_id', '5', 5, 'parent_id', '11', 11],
    'order item' => ['sales/order_item', 'qty_ordered', '2.0000', 2.0, 'product_id', '7', 7],
    'order payment' => ['sales/order_payment', 'amount_paid', '12.3400', 12.34, 'parent_id', '11', 11],
    'invoice item' => ['sales/order_invoice_item', 'price', '5.0000', 5.0, 'order_item_id', '3', 3],
    'shipment item' => ['sales/order_shipment_item', 'weight', '1.5000', 1.5, 'order_item_id', '3', 3],
    'creditmemo item' => ['sales/order_creditmemo_item', 'price', '5.0000', 5.0, 'order_item_id', '3', 3],
    'customer' => ['customer/customer', 'group_id', '1', 1, 'default_billing', '5', 5],
    'customer gender' => ['customer/customer', 'gender', '1', 1, 'website_id', '1', 1],
]);

it('returns typed values from database-shaped input', function (string $model, string $key1, string $in1, float|int|bool $out1, string $key2, string $in2, int|bool $out2) {
    $object = Mage::getModel($model);
    $object->setData($key1, $in1);
    $object->setData($key2, $in2);

    $getter1 = 'get' . str_replace('_', '', ucwords($key1, '_'));
    $getter2 = 'get' . str_replace('_', '', ucwords($key2, '_'));

    expect($object->$getter1())->toBe($out1)
        ->and($object->$getter2())->toBe($out2);
})->with('typed accessor round trips');

it('returns null for a key the model does not hold', function () {
    expect(Mage::getModel('sales/order')->getGrandTotal())->toBeNull()
        ->and(Mage::getModel('sales/quote')->getCustomerId())->toBeNull()
        ->and(Mage::getModel('sales/quote_address')->getCustomerAddressId())->toBeNull()
        ->and(Mage::getModel('customer/customer')->getDefaultBilling())->toBeNull();
});

it('keeps string columns as strings', function () {
    $order = Mage::getModel('sales/order')->setData('billing_firstname', '007')->setData('increment_id', '100000001');
    $customer = Mage::getModel('customer/customer')->setData('increment_id', '000001');
    $address = Mage::getModel('customer/address')->setData('postcode', '01234');

    expect($order->getBillingFirstname())->toBe('007')
        ->and($order->getIncrementId())->toBe('100000001')
        ->and($customer->getIncrementId())->toBe('000001')
        ->and($address->getPostcode())->toBe('01234');
});

it('reads the computed total due through the order getData override', function () {
    $order = Mage::getModel('sales/order');
    $order->setData('grand_total', '10.0000');
    $order->setData('total_paid', '4.0000');

    expect($order->getGrandTotal())->toBe(10.0)
        ->and($order->getData('total_due'))->toBe(6.0);
});

it('decrypts the card number through the payment getData override', function () {
    $payment = Mage::getModel('sales/quote_payment');
    $payment->setCcNumberEnc($payment->encrypt('4111111111111111'));

    expect($payment->getCcNumberEnc())->toBeString()
        ->and($payment->getCcNumber())->toBe('4111111111111111');
});

it('returns the store group name from the second line of store_name', function () {
    $order = Mage::getModel('sales/order');
    $order->setData('store_name', "Main Website\nMain Store\nDefault Store View");

    expect($order->getStoreGroupName())->toBe('Main Store');
});

it('falls back to the parent id for the customer address customer id', function () {
    $address = Mage::getModel('customer/address');
    $address->setData('parent_id', '7');

    expect($address->getCustomerId())->toBe(7)
        ->and($address->getParentId())->toBe(7);
});
