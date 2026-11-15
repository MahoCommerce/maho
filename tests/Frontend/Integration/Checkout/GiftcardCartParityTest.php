<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The gift card form of the cart page and the gift card endpoints of a guest cart apply and remove a gift card
 * through Maho_Giftcard_Service_Giftcard::applyToCart() and removeFromCart() (#1348). Each test gives the same code
 * to both transports and compares the outcome.
 */

function giftcardCartCard(float $balance = 5.00): Maho_Giftcard_Model_Giftcard
{
    $giftcard = Mage::getModel('giftcard/giftcard')
        ->setCode(Mage::helper('giftcard')->generateCode())
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
        ->setBalance($balance)
        ->setInitialBalance($balance);
    $giftcard->save();

    return $giftcard;
}

/**
 * Post $params to the action $action of the gift card form of the cart page, with $quote as the cart of the session.
 *
 * @param array<string, string> $params
 * @return array{success: list<string>, errors: list<string>}
 */
function giftcardCartViaWeb(Mage_Sales_Model_Quote $quote, string $action, array $params): array
{
    $session = Mage::getSingleton('checkout/session');
    $session->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    $session->getMessages(true);

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/giftcard/cart/{$action}", 'POST', $params));
    $request->setRouteName('giftcard')->setControllerName('cart')->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    new Maho_Giftcard_CartController($request, new Mage_Core_Controller_Response_Http())->{$action . 'Action'}();

    $messages = ['success' => [], 'errors' => []];
    foreach ($session->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * Post $code to POST /api/rest/v2/guest-carts/{id}/giftcards.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftcardCartApplyViaApi(Mage_Sales_Model_Quote $quote, string $code): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/giftcards", ['giftcardCode' => $code]);
    $processor = new CartProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'apply_guest_giftcard'), ['id' => $maskedId], ['request' => $request]));
}

/**
 * Call DELETE /api/rest/v2/guest-carts/{id}/giftcards/{code}.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftcardCartRemoveViaApi(Mage_Sales_Model_Quote $quote, string $code): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('DELETE', "/api/rest/v2/guest-carts/{$maskedId}/giftcards/{$code}");
    $processor = new CartProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Delete(name: 'remove_guest_giftcard'), ['id' => $maskedId, 'code' => $code], ['request' => $request]));
}

/**
 * @return array<string, float> the code of each gift card of $quote => its amount
 */
function giftcardCartCodes(Mage_Sales_Model_Quote $quote): array
{
    $codes = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getGiftcardCodes();

    return array_map(floatval(...), $codes ? Mage::helper('core')->jsonDecode($codes) : []);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $this->carts = [CartParity::cart(), CartParity::cart()];
    $this->giftcard = giftcardCartCard();
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
    $this->giftcard->delete();
});

it('applies a gift card on both transports', function () {
    [$webCart, $apiCart] = $this->carts;
    $code = (string) $this->giftcard->getCode();

    $web = giftcardCartViaWeb($webCart, 'apply', ['giftcard_code' => " {$code} "]);
    $api = giftcardCartApplyViaApi($apiCart, " {$code} ");

    expect($web)->toBe(['success' => ["Gift card \"{$code}\" was applied."], 'errors' => []])
        ->and($api['status'])->toBe(200)
        ->and(giftcardCartCodes($webCart))->toBe([$code => 5.0])
        ->and(giftcardCartCodes($apiCart))->toBe([$code => 5.0]);
});

it('refuses an unknown code with the same message', function () {
    [$webCart, $apiCart] = $this->carts;

    $web = giftcardCartViaWeb($webCart, 'apply', ['giftcard_code' => 'PEST-NO-SUCH-CARD']);
    $api = giftcardCartApplyViaApi($apiCart, 'PEST-NO-SUCH-CARD');

    expect($web)->toBe(['success' => [], 'errors' => ['Gift card "PEST-NO-SUCH-CARD" is not valid.']])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(['Gift card "PEST-NO-SUCH-CARD" is not valid.'])
        ->and(giftcardCartCodes($webCart))->toBe([])
        ->and(giftcardCartCodes($apiCart))->toBe([]);
});

it('refuses a card that is already on the cart with the same message', function () {
    [$webCart, $apiCart] = $this->carts;
    $code = (string) $this->giftcard->getCode();
    foreach ($this->carts as $cart) {
        Mage::getService('giftcard/giftcard')->applyToCart($cart, $code, actor: Mage_Checkout_Model_Actor::Staff);
    }

    $web = giftcardCartViaWeb($webCart, 'apply', ['giftcard_code' => strtolower($code)]);
    $api = giftcardCartApplyViaApi($apiCart, strtolower($code));

    expect($web['errors'])->toBe(["Gift card \"{$code}\" is already applied."])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(["Gift card \"{$code}\" is already applied."]);
});

it('removes a gift card on both transports, and the case of the code does not matter', function () {
    [$webCart, $apiCart] = $this->carts;
    $code = (string) $this->giftcard->getCode();
    foreach ($this->carts as $cart) {
        Mage::getService('giftcard/giftcard')->applyToCart($cart, $code, actor: Mage_Checkout_Model_Actor::Staff);
    }

    $web = giftcardCartViaWeb($webCart, 'remove', ['code' => strtolower($code)]);
    $api = giftcardCartRemoveViaApi($apiCart, strtolower($code));

    expect($web)->toBe(['success' => ['Gift card was removed.'], 'errors' => []])
        ->and($api['status'])->toBe(200)
        ->and(giftcardCartCodes($webCart))->toBe([])
        ->and(giftcardCartCodes($apiCart))->toBe([]);
    foreach ($this->carts as $cart) {
        expect((float) Mage::getModel('sales/quote')->loadByIdWithoutStore($cart->getId())->getGiftcardAmount())->toBe(0.0);
    }
});

describe('kept difference: a card that is not on the cart', function () {
    it('is a 404 on the API, and the cart page shows no message', function () {
        [$webCart, $apiCart] = $this->carts;
        $code = (string) $this->giftcard->getCode();

        $web = giftcardCartViaWeb($webCart, 'remove', ['code' => $code]);
        $api = giftcardCartRemoveViaApi($apiCart, $code);

        expect($web)->toBe(['success' => [], 'errors' => []])
            ->and($api['status'])->toBe(404)
            ->and($api['errors'])->toBe(["Gift card \"{$code}\" is not applied to this cart"]);
    });
});

describe('the limit of codes', function () {
    it('counts the codes of the cart page and of the API together, and not the codes of staff', function () {
        [$webCart, $apiCart] = $this->carts;
        Mage::app()->getStore()->setConfig(Maho_Giftcard_Service_Giftcard::XML_PATH_BALANCE_RATE_LIMIT, '2');

        giftcardCartViaWeb($webCart, 'apply', ['giftcard_code' => 'PEST-NO-SUCH-CARD-1']);
        giftcardCartApplyViaApi($apiCart, 'PEST-NO-SUCH-CARD-2');
        $web = giftcardCartViaWeb($webCart, 'apply', ['giftcard_code' => (string) $this->giftcard->getCode()]);
        $api = giftcardCartApplyViaApi($apiCart, (string) $this->giftcard->getCode());
        Mage::getService('giftcard/giftcard')->applyToCart($apiCart, (string) $this->giftcard->getCode(), actor: Mage_Checkout_Model_Actor::Staff);

        expect($web['errors'])->toBe(['Too many requests. Please try again later.'])
            ->and($api['status'])->toBe(429)
            ->and(giftcardCartCodes($webCart))->toBe([])
            ->and(giftcardCartCodes($apiCart))->toBe([(string) $this->giftcard->getCode() => 5.0]);
    });
});
