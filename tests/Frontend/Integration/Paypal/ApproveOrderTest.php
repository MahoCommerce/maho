<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

/**
 * Save a quote that the CHECKOUT.ORDER.APPROVED webhook already turned into an order.
 */
function approveOrderPlacedQuote(): Mage_Sales_Model_Quote
{
    $store = Mage::app()->getStore();
    $quote = Mage::getModel('sales/quote')
        ->setStoreId((int) $store->getId())
        ->setQuoteCurrencyCode($store->getCurrentCurrencyCode())
        ->setIsActive(false);
    $quote->save();
    return $quote;
}

function approveOrderPlacedOrder(int $quoteId, string $paypalOrderId): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('APPROVE-' . uniqid())
        ->setQuoteId($quoteId)
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setSubtotal(0)
        ->setGrandTotal(0)
        ->setTotalQtyOrdered(0);
    $order->save();
    Mage::getModel('sales/order_payment')
        ->setParentId((int) $order->getId())
        ->setMethod(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT)
        ->setData('paypal_order_id', $paypalOrderId)
        ->save();
    return $order;
}

/**
 * @return array<string, mixed>
 */
function approveOrderDispatch(string $paypalOrderId): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/paypal/checkout/approveOrder', 'POST', [
            'paypal_order_id' => $paypalOrderId,
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]),
    );
    $request->setRouteName('paypal')
        ->setControllerName('checkout')
        ->setActionName('approveOrder')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Maho_Paypal_CheckoutController($request, new Mage_Core_Controller_Response_Http());
    $controller->dispatch('approveOrder');

    return Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    unset($_SESSION['checkout']);
    Mage::unregister('_singleton/checkout/session');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

    $this->quote = approveOrderPlacedQuote();
    $this->orders = [];
    Mage::getSingleton('checkout/session')->setQuoteId((int) $this->quote->getId());
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        $order->delete();
    }
    $this->quote->delete();
    Mage::unregister('isSecureArea');
    unset($_SESSION['checkout']);
    Mage::unregister('_singleton/checkout/session');
});

it('redirects to the success page when the webhook already placed the order of the session quote', function () {
    $paypalOrderId = 'WEBHOOK' . strtoupper(bin2hex(random_bytes(4)));
    $order = approveOrderPlacedOrder((int) $this->quote->getId(), $paypalOrderId);
    $this->orders[] = $order;

    $result = approveOrderDispatch($paypalOrderId);

    expect($result['success'])->toBeTrue()
        ->and($result['redirect_url'])->toContain('checkout/onepage/success');
    $checkoutSession = Mage::getSingleton('checkout/session');
    expect((int) $checkoutSession->getLastSuccessQuoteId())->toBe((int) $this->quote->getId())
        ->and((int) $checkoutSession->getLastOrderId())->toBe((int) $order->getId());
});

it('refuses a placed PayPal order that belongs to another quote', function () {
    $otherQuote = approveOrderPlacedQuote();
    $paypalOrderId = 'OTHER' . strtoupper(bin2hex(random_bytes(4)));
    $this->orders[] = approveOrderPlacedOrder((int) $otherQuote->getId(), $paypalOrderId);

    try {
        $result = approveOrderDispatch($paypalOrderId);
    } finally {
        Mage::register('isSecureArea', true, true);
        $otherQuote->delete();
        Mage::unregister('isSecureArea');
    }

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('PayPal order does not belong to this cart.')
        ->and(Mage::getSingleton('checkout/session')->getLastOrderId())->toBeNull();
});
