<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The gift card product type checks the delivery date of the buy request, so the add to cart of the storefront and
 * of the API accept the same dates (#1348).
 */

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function giftcardDateViaWeb(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product, string $date): array
{
    Mage::getSingleton('checkout/session')->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    Mage::getSingleton('checkout/cart')->unsetData('quote');

    // getParams() reads the superglobals, as on a real request
    $_POST = [
        'product' => $product->getId(), 'giftcard_amount' => 25, 'giftcard_recipient_name' => 'Friend',
        'giftcard_recipient_email' => 'friend@example.com', 'giftcard_delivery_date' => $date,
    ];
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/add', 'POST', $_POST));
    $request->setRouteName('checkout')->setControllerName('cart')->setActionName('add')->setDispatched(true);
    Mage::app()->setRequest($request);
    try {
        new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http())->addAction();
    } finally {
        $_POST = [];
    }

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('checkout/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftcardDateViaApi(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product, string $date): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/items", [
        'sku' => $product->getSku(), 'giftcardAmount' => 25, 'giftcardRecipientName' => 'Friend',
        'giftcardRecipientEmail' => 'friend@example.com', 'giftcardDeliveryDate' => $date,
    ]);
    $processor = new CartProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'add_guest_item'), ['id' => $maskedId], ['request' => $request]));
}

/**
 * @return list<string> the delivery date of each gift card line of $quote
 */
function giftcardDateLines(Mage_Sales_Model_Quote $quote): array
{
    $dates = [];
    foreach (Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllItems() as $item) {
        $dates[] = (string) $item->getBuyRequest()->getData('giftcard_delivery_date');
    }
    return $dates;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->carts = [CartParity::cart(withItem: false), CartParity::cart(withItem: false)];
    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId('giftcard')
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-giftcard-date-' . uniqid())->setName('Pest gift card')->setPrice(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->setData('giftcard_type', 'fixed')
        ->setData('giftcard_amounts', '25,50')
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $this->product->save();
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
    Mage::register('isSecureArea', true, true);
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('refuses a delivery date that is not a date in the format YYYY-MM-DD with the same error on the field', function (string $date) {
    [$webCart, $apiCart] = $this->carts;

    $web = giftcardDateViaWeb($webCart, $this->product, $date);
    $api = giftcardDateViaApi($apiCart, $this->product, $date);

    $message = 'Please enter the delivery date in the format YYYY-MM-DD.';
    expect($web['errors'])->toBe([$message])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and($api['fields'])->toBe(['giftcardDeliveryDate'])
        ->and(giftcardDateLines($webCart))->toBe([])
        ->and(giftcardDateLines($apiCart))->toBe([]);
})->with([
    'text' => ['next friday'],
    'another format' => ['24/12/2026'],
    'a day that does not exist' => ['2026-02-30'],
]);

it('keeps a valid delivery date in the cart on both transports', function () {
    [$webCart, $apiCart] = $this->carts;
    $date = Mage::app()->getLocale()->utcToStore()->modify('+5 days')->format(Mage_Core_Model_Locale::DATE_FORMAT);

    $web = giftcardDateViaWeb($webCart, $this->product, $date);
    $api = giftcardDateViaApi($apiCart, $this->product, $date);

    expect($web['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and(giftcardDateLines($webCart))->toBe([$date])
        ->and(giftcardDateLines($apiCart))->toBe([$date]);
});
