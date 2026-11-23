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
function orderCreateGuestForm(?bool $isGuest): Mage_Adminhtml_Block_Sales_Order_Create_Form
{
    Mage::getSingleton('adminhtml/session_quote')
        ->setCustomerId(null)
        ->setCustomerIsGuest($isGuest)
        ->setStoreId(1);

    return Mage::app()->getLayout()->createBlock('adminhtml/sales_order_create_form');
}

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->clear();
});

it('shows the order data, not the customer selector, for a guest', function () {
    $form = orderCreateGuestForm(true);

    expect($form->getCustomerSelectorDisplay())->toBe('none')
        ->and($form->getDataSelectorDisplay())->toBe('block')
        ->and(Mage::helper('core')->jsonDecode($form->getOrderDataJson())['is_guest'])->toBeTrue();
});

it('asks for a customer when the session has no customer and no guest', function () {
    $form = orderCreateGuestForm(null);

    expect($form->getCustomerSelectorDisplay())->toBe('block')
        ->and($form->getDataSelectorDisplay())->toBe('none')
        ->and(Mage::helper('core')->jsonDecode($form->getOrderDataJson())['is_guest'])->toBeFalse();
});
