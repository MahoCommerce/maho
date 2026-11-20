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

uses(Tests\MahoFrontendTestCase::class);

function savePaymentTestCart(): Mage_Sales_Model_Quote
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();
    if (!$product->getId()) {
        test()->markTestSkipped('No simple product available for testing');
    }

    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getStore()->getId())->setIsActive(true);
    $quote->addProduct($product, 1);
    $quote->collectTotals()->save();

    return $quote;
}

function savePaymentTestReload(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

/**
 * Post $post to the savePayment action of the one-page checkout with $quote in the session, and return the JSON answer.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function savePaymentTestPost(Mage_Sales_Model_Quote $quote, array $post): array
{
    $session = Mage::getSingleton('checkout/session');
    $session->replaceQuote(savePaymentTestReload($quote));
    $session->setCartWasUpdated(false);
    // The controller takes the checkout model as a singleton, which keeps the cart of an earlier call
    Mage::unregister('_singleton/checkout/type_onepage');

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/onepage/savePayment', 'POST', $post));
    $request->setRouteName('checkout')->setControllerName('onepage')->setActionName('savePayment')->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Checkout_OnepageController {
        #[\Override]
        public function loadLayout($handles = null, $generateBlocks = true, $generateXml = true)
        {
            return $this;
        }

        #[\Override]
        protected function _getReviewHtml()
        {
            return '';
        }
    };
    $controller->savePaymentAction();

    $body = (string) $controller->getResponse()->getBody();

    return $body === '' ? [] : Mage::helper('core')->jsonDecode($body);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->carts = [savePaymentTestCart(), savePaymentTestCart()];
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
});

describe('the payment form of the storefront', function () {
    it('does not write the payment of another cart', function () {
        [$cart, $otherCart] = $this->carts;
        Mage::app()->getStore()->setConfig('payment/banktransfer/active', '1');
        savePaymentTestPost($otherCart, ['payment' => ['method' => 'checkmo']]);
        $otherPaymentId = (int) savePaymentTestReload($otherCart)->getPayment()->getId();

        savePaymentTestPost($cart, ['payment' => ['method' => 'banktransfer', 'payment_id' => (string) $otherPaymentId]]);

        $otherPayment = savePaymentTestReload($otherCart)->getPayment();
        expect((int) $otherPayment->getId())->toBe($otherPaymentId)
            ->and($otherPayment->getMethod())->toBe('checkmo');
    });

    it('does not take the additional information of the form', function () {
        Mage::app()->getStore()->setConfig('payment/banktransfer/active', '1');

        $web = savePaymentTestPost($this->carts[0], ['payment' => [
            'method' => 'banktransfer',
            'additional_information' => ['paypal_capture_id' => 'pest-capture'],
        ]]);

        expect($web)->not->toHaveKey('error')
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getAdditionalInformation('paypal_capture_id'))->toBeNull();
    });
});

describe('the one-step checkout auto-save of a payment method (method_only)', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/authorizenet/active', '1');
        $store->setConfig('payment/authorizenet/cctypes', 'VI');
        $store->setConfig('payment/authorizenet/useccv', '0');
    });

    it('selects a card method before its card fields are filled in', function () {
        $web = savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'authorizenet'], 'method_only' => '1']);

        expect($web)->not->toHaveKey('error')
            ->and($web['goto_section'] ?? null)->toBe('review')
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getMethod())->toBe('authorizenet');
    });

    it('replaces the method that the cart already has with a card method', function () {
        savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'checkmo']]);

        $web = savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'authorizenet'], 'method_only' => '1']);

        expect($web)->not->toHaveKey('error')
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getMethod())->toBe('authorizenet');
    });

    it('does not change the full save, which still refuses a card method without its card fields', function () {
        $web = savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'authorizenet']]);

        expect($web)->toHaveKey('error')
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getMethod())->toBeNull();
    });

    it('refuses a method that the cart cannot use', function () {
        Mage::app()->getStore()->setConfig('payment/checkmo/min_order_total', '999999');

        expect(savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'checkmo'], 'method_only' => '1']))
            ->toBe(['error' => 'The requested Payment Method is not available.'])
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getMethod())->toBeNull();
    });

    it('refuses a method that the store does not have', function () {
        expect(savePaymentTestPost($this->carts[0], ['payment' => ['method' => 'pest_unknown'], 'method_only' => '1']))
            ->toBe(['error' => 'The requested Payment Method is not available.'])
            ->and(savePaymentTestReload($this->carts[0])->getPayment()->getMethod())->toBeNull();
    });
});
