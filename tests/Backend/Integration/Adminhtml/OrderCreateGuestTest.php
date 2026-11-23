<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A guest order has no customer ID. Admin order creation keeps the guest in the session, and
 * a reorder of a guest order puts it there too. The form once read only the customer ID, so a
 * reorder of a guest order asked for a customer again and the page script lost the guest.
 */
function orderCreateGuestForm(?int $customerId, ?bool $isGuest): Mage_Adminhtml_Block_Sales_Order_Create_Form
{
    Mage::getSingleton('adminhtml/session_quote')
        ->setCustomerId($customerId)
        ->setCustomerIsGuest($isGuest)
        ->setStoreId(1);

    return Mage::app()->getLayout()->createBlock('adminhtml/sales_order_create_form');
}

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->clear();
});

it('shows the order data, not the customer selector, for a guest', function () {
    $form = orderCreateGuestForm(null, true);

    expect($form->getCustomerSelectorDisplay())->toBe('none')
        ->and($form->getDataSelectorDisplay())->toBe('block')
        ->and($form->getStoreSelectorDisplay())->toBe('none');
});

it('tells the page script that the order is for a guest', function () {
    $data = Mage::helper('core')->jsonDecode(orderCreateGuestForm(null, true)->getOrderDataJson());

    expect($data['is_guest'])->toBeTrue();
});

it('asks for a customer when the session has no customer and no guest', function () {
    $form = orderCreateGuestForm(null, null);

    expect($form->getCustomerSelectorDisplay())->toBe('block')
        ->and($form->getDataSelectorDisplay())->toBe('none')
        ->and(Mage::helper('core')->jsonDecode($form->getOrderDataJson())['is_guest'])->toBeFalse();
});

it('puts a reordered guest order in guest mode', function () {
    $order = Mage::getResourceModel('sales/order_collection')
        ->addFieldToFilter('customer_is_guest', 1)
        ->setPageSize(1)
        ->getFirstItem();
    if (!$order->getId()) {
        $this->markTestSkipped('The test store has no guest order.');
    }

    Mage::getSingleton('adminhtml/sales_order_create')->initFromOrder(Mage::getModel('sales/order')->load((int) $order->getId()));
    $form = Mage::app()->getLayout()->createBlock('adminhtml/sales_order_create_form');

    expect($form->getCustomerSelectorDisplay())->toBe('none')
        ->and($form->getDataSelectorDisplay())->toBe('block');
});
