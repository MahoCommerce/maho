<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The promo code box of the cart page and POST /guest-carts/{id}/coupon apply a coupon through
 * Mage_Checkout_Service_Cart::applyCoupon() (#1348). Each test gives the same code to both transports and
 * compares the outcome.
 */

/**
 * A rule of 10% for guests and customers of the main website, with a coupon code.
 */
function couponParityRule(?string $toDate = null): Mage_SalesRule_Model_Rule
{
    $rule = Mage::getModel('salesrule/rule');
    $rule->setName('Coupon parity ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
        ->setCustomerGroupIds([0, 1])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        ->setCouponCode('PARITY-' . strtoupper(uniqid()))
        ->setToDate($toDate)
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10);
    $rule->save();
    return $rule;
}

function couponParityStoredCode(Mage_Sales_Model_Quote $quote): string
{
    return (string) Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getCouponCode();
}

/**
 * Post $code to the promo code box of the cart page, with $quote as the cart of the session.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function couponViaWeb(Mage_Sales_Model_Quote $quote, string $code): array
{
    Mage::getSingleton('checkout/session')->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    Mage::getSingleton('checkout/cart')->unsetData('quote');

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/couponPost', 'POST', ['coupon_code' => $code]));
    $request->setRouteName('checkout')->setControllerName('cart')->setActionName('couponPost')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http())->couponPostAction();

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('checkout/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * Post $code as the body of POST /api/rest/v2/guest-carts/{id}/coupon.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function couponViaApi(Mage_Sales_Model_Quote $quote, string $code, ?Security $security = null): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/coupon", ['code' => $code]);
    $processor = new CartProcessor($security ?? CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'apply_guest_coupon'), ['id' => $maskedId], ['request' => $request]));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $this->rule = couponParityRule();
    $this->carts = [];
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
    $this->rule->delete();
});

it('applies a coupon on both transports', function () {
    $code = (string) $this->rule->getCouponCode();
    $this->carts = [$webCart = CartParity::cart(), $apiCart = CartParity::cart()];

    $web = couponViaWeb($webCart, $code);
    $api = couponViaApi($apiCart, $code);

    expect($web)->toBe(['success' => ["Coupon code \"{$code}\" was applied."], 'errors' => []])
        ->and($api['status'])->toBe(200)
        ->and(couponParityStoredCode($webCart))->toBe($code)
        ->and(couponParityStoredCode($apiCart))->toBe($code);
});

it('refuses an empty code with the same message', function () {
    $this->carts = [$cart = CartParity::cart()];

    $web = couponViaWeb($cart, '  ');
    $api = couponViaApi($cart, '');

    expect($web['errors'])->toBe(['Coupon code is required'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['couponCode'])
        ->and($api['errors'])->toBe(['Coupon code is required']);
});

it('refuses a coupon for an empty cart with the same message', function () {
    $code = (string) $this->rule->getCouponCode();
    $this->carts = [$cart = CartParity::cart(withItem: false)];

    $web = couponViaWeb($cart, $code);
    $api = couponViaApi($cart, $code);

    expect($web['errors'])->toBe(['Your shopping cart is empty.'])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['Your shopping cart is empty.'])
        ->and(couponParityStoredCode($cart))->toBe('');
});

it('keeps the coupon of the cart when a code does not apply', function () {
    $code = (string) $this->rule->getCouponCode();
    $expired = couponParityRule(toDate: '2001-01-01');
    $this->carts = [$cart = CartParity::cart()];
    Mage::getService('checkout/cart')->applyCoupon($cart, $code, Mage_Checkout_Model_Actor::Staff);

    try {
        $web = couponViaWeb($cart, (string) $expired->getCouponCode());
        $api = couponViaApi($cart, (string) $expired->getCouponCode());
    } finally {
        $expired->delete();
    }

    expect($web['errors'])->toBe(["Promo code \"{$expired->getCouponCode()}\" is not valid."])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(["Coupon code '{$expired->getCouponCode()}' could not be applied"])
        ->and(couponParityStoredCode($cart))->toBe($code);
});

it('keeps the coupon of the cart when the promo code box applies a gift card', function () {
    $code = (string) $this->rule->getCouponCode();
    $this->carts = [$cart = CartParity::cart()];
    Mage::getService('checkout/cart')->applyCoupon($cart, $code, Mage_Checkout_Model_Actor::Staff);
    $giftcard = Mage::getModel('giftcard/giftcard')
        ->setCode(Mage::helper('giftcard')->generateCode())
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
        ->setBalance(5.00)
        ->setInitialBalance(5.00);
    $giftcard->save();

    try {
        $web = couponViaWeb($cart, (string) $giftcard->getCode());
    } finally {
        $giftcard->delete();
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($cart->getId());
    expect($web['success'])->toBe(["Gift card \"{$giftcard->getCode()}\" was applied."])
        ->and((string) $stored->getCouponCode())->toBe($code)
        ->and((string) $stored->getGiftcardCodes())->toContain((string) $giftcard->getCode());
});

it('applies the coupon of a code that is also a gift card, on the cart page and in the admin GraphQL API', function () {
    $code = (string) $this->rule->getCouponCode();
    $this->carts = [$webCart = CartParity::cart(), $staffCart = CartParity::cart()];
    $giftcard = Mage::getModel('giftcard/giftcard')
        ->setCode($code)
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
        ->setBalance(5.00)
        ->setInitialBalance(5.00);
    $giftcard->save();

    try {
        $web = couponViaWeb($webCart, $code);
        new Mage\Checkout\Api\GraphQL\CartMutationHandler(new Mage\Checkout\Api\CartMapper())
            ->handleApplyCoupon(['cartId' => (int) $staffCart->getId(), 'couponCode' => $code]);
    } finally {
        $giftcard->delete();
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($staffCart->getId());
    expect($web['success'])->toBe(["Coupon code \"{$code}\" was applied."])
        ->and((string) $stored->getCouponCode())->toBe($code)
        ->and($stored->getGiftcardCodes())->toBeNull();
});

it('removes the coupon of the cart on both transports', function () {
    $code = (string) $this->rule->getCouponCode();
    $this->carts = [$webCart = CartParity::cart(), $apiCart = CartParity::cart()];
    foreach ($this->carts as $cart) {
        Mage::getService('checkout/cart')->applyCoupon($cart, $code, Mage_Checkout_Model_Actor::Staff);
    }
    $discounted = (float) Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId())->getGrandTotal();

    Mage::getSingleton('checkout/session')->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId()));
    Mage::getSingleton('checkout/cart')->unsetData('quote');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/couponPost', 'POST', ['remove' => '1']));
    $request->setRouteName('checkout')->setControllerName('cart')->setActionName('couponPost')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http())->couponPostAction();
    $web = [];
    foreach (Mage::getSingleton('checkout/session')->getMessages(true)->getItems() as $message) {
        $web[] = [$message->getType(), $message->getText()];
    }

    $maskedId = (string) $apiCart->getData('masked_quote_id');
    $apiRequest = AdminApiParity::jsonRequest('DELETE', "/api/rest/v2/guest-carts/{$maskedId}/coupon");
    $api = AdminApiParity::api($apiRequest, fn() => new CartProcessor(CartParity::guestSecurity())
        ->process(null, new Delete(name: 'remove_guest_coupon'), ['id' => $maskedId], ['request' => $apiRequest]));

    $webStored = Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId());
    $apiStored = Mage::getModel('sales/quote')->loadByIdWithoutStore($apiCart->getId());
    expect($web)->toBe([[Mage_Core_Model_Message::SUCCESS, 'Coupon code was removed.']])
        ->and($api['status'])->toBe(200)
        ->and((string) $webStored->getCouponCode())->toBe('')
        ->and((string) $apiStored->getCouponCode())->toBe('')
        ->and((float) $webStored->getGrandTotal())->toBeGreaterThan($discounted)
        ->and((float) $apiStored->getGrandTotal())->toBe((float) $webStored->getGrandTotal());
});

describe('the limit of codes', function () {
    it('counts the codes of the cart page and of the API together', function () {
        Mage::app()->getStore()->setConfig(Mage_SalesRule_Service_Rule::XML_PATH_CHECK_RATE_LIMIT, '2');
        $this->carts = [$cart = CartParity::cart()];

        $first = couponViaWeb($cart, 'NOPE-1');
        $second = couponViaApi($cart, 'NOPE-2');
        $third = couponViaWeb($cart, 'NOPE-3');
        $fourth = couponViaApi($cart, 'NOPE-4');

        expect($first['errors'])->toBe(['Promo code "NOPE-1" is not valid.'])
            ->and($second['status'])->toBe(422)
            ->and($third['errors'])->toBe(['Too many requests. Please try again later.'])
            ->and($fourth['status'])->toBe(429)
            ->and($fourth['errors'])->toBe(['Too many requests. Please try again later.']);
    });

    it('does not count the codes of a staff token', function () {
        Mage::app()->getStore()->setConfig(Mage_SalesRule_Service_Rule::XML_PATH_CHECK_RATE_LIMIT, '1');
        $this->carts = [$cart = CartParity::cart()];

        $first = couponViaApi($cart, 'NOPE-1', AdminApiParity::adminSecurity());
        $second = couponViaApi($cart, 'NOPE-2', AdminApiParity::adminSecurity());
        $guest = couponViaApi($cart, 'NOPE-3');

        expect($first['status'])->toBe(422)
            ->and($second['status'])->toBe(422)
            ->and($guest['status'])->toBe(422);
    });
});
