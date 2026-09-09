<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A payment method's configured "New Order Status" is applied to the state the
 * placement computes. When the two do not match, the state wins and the status
 * falls back to that state's default.
 */

function paymentPlaceOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setCustomerIsGuest(1)
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGrandTotal(100)
        ->setBaseGrandTotal(100);
    $order->setBillingAddress(Mage::getModel('sales/order_address')->setAddressType('billing')->setCountryId('US'));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));

    return $order;
}

afterEach(function (): void {
    Mage::app()->getStore(1)->setConfig('payment/checkmo/order_status', 'pending');
});

it('falls back to the default status of the state when the configured status belongs to another state', function (): void {
    Mage::app()->getStore(1)->setConfig('payment/checkmo/order_status', 'processing');
    $order = paymentPlaceOrder();

    $order->getPayment()->place();

    expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_NEW);
    expect($order->getStatus())->toBe('pending');
});

it('keeps a configured status that is assigned to the state', function (): void {
    Mage::app()->getStore(1)->setConfig('payment/checkmo/order_status', 'pending');
    $order = paymentPlaceOrder();

    $order->getPayment()->place();

    expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_NEW);
    expect($order->getStatus())->toBe('pending');
});
