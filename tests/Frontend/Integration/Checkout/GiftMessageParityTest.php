<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Put;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

require_once __DIR__ . '/OnepageDriver.php';

uses(Tests\MahoFrontendTestCase::class);

/**
 * The shipping method step of the checkout and PUT /guest-carts/{id}/gift-message save a gift message through
 * Mage_Checkout_Service_Cart::setGiftMessage() (#1348). Each test gives the same message to both transports and
 * compares the outcome.
 */

/**
 * Post the shipping method step of the one page checkout for $quote, with the gift message fields as the checkout
 * form names them. The step fires checkout_controller_onepage_save_shipping_method, and the gift message observer of
 * the frontend area saves the messages.
 *
 * @param array<string, array<string, string>> $giftMessages
 */
function giftMessageViaWeb(Mage_Sales_Model_Quote $quote, array $giftMessages): void
{
    Mage::getService('checkout/cart')->setShippingAddress(
        Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()),
        \Mage\Checkout\Api\CartRequest::mapAddress([
            'firstName' => 'Gift', 'lastName' => 'Message', 'street' => ['1 Main St'], 'city' => 'Los Angeles',
            'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
        ]),
    );
    // A real request loads the observers of the frontend area in preDispatch()
    $hadFrontendEvents = (fn(): bool => isset($this->_events[Mage_Core_Model_App_Area::AREA_FRONTEND]))->call(Mage::app());
    Mage::app()->addEventArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
    try {
        $result = checkoutOnepagePost($quote, 'saveShippingMethod', ['shipping_method' => 'flatrate_flatrate', 'giftmessage' => $giftMessages]);
    } finally {
        if (!$hadFrontendEvents) {
            (function (): void {
                unset($this->_events[Mage_Core_Model_App_Area::AREA_FRONTEND]);
            })->call(Mage::app());
        }
    }
    expect($result['goto_section'] ?? null)->toBe('payment');
}

/**
 * Put $body to /api/rest/v2/guest-carts/{id}/gift-message.
 *
 * @param array<string, string> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftMessageViaApi(Mage_Sales_Model_Quote $quote, array $body): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('PUT', "/api/rest/v2/guest-carts/{$maskedId}/gift-message", $body);
    $processor = new CartProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Put(name: 'set_guest_cart_gift_message'), ['id' => $maskedId], ['request' => $request]));
}

/**
 * @return array{sender: string, recipient: string, message: string}|null
 */
function giftMessageStored(Mage_Sales_Model_Quote $quote): ?array
{
    $messageId = (int) Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getGiftMessageId();
    $message = Mage::getModel('giftmessage/message')->load($messageId);
    return $message->getId()
        ? ['sender' => (string) $message->getSender(), 'recipient' => (string) $message->getRecipient(), 'message' => (string) $message->getMessage()]
        : null;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER, '1');
    $this->carts = [CartParity::cart(), CartParity::cart()];
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        Mage::getService('checkout/cart')->removeGiftMessage($cart, null);
        $cart->delete();
    }
});

it('saves the gift message of the cart on both transports', function () {
    [$webCart, $apiCart] = $this->carts;

    giftMessageViaWeb($webCart, ["quote_{$webCart->getId()}" => ['type' => 'quote', 'from' => ' Ann ', 'to' => 'Bob ', 'message' => 'Happy birthday']]);
    $api = giftMessageViaApi($apiCart, ['sender' => ' Ann ', 'recipient' => 'Bob ', 'message' => 'Happy birthday']);

    $expected = ['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Happy birthday'];
    expect($api['status'])->toBe(200)
        ->and(giftMessageStored($webCart))->toBe($expected)
        ->and(giftMessageStored($apiCart))->toBe($expected);
});

it('removes the gift message of the cart for an empty message on both transports', function () {
    [$webCart, $apiCart] = $this->carts;
    foreach ($this->carts as $cart) {
        Mage::getService('checkout/cart')->setGiftMessage($cart, null, 'Ann', 'Bob', 'Happy birthday');
    }

    giftMessageViaWeb($webCart, ["quote_{$webCart->getId()}" => ['type' => 'quote', 'from' => 'Ann', 'to' => 'Bob', 'message' => ' ']]);
    $api = giftMessageViaApi($apiCart, ['sender' => 'Ann', 'recipient' => 'Bob', 'message' => ' ']);

    expect($api['status'])->toBe(200)
        ->and(giftMessageStored($webCart))->toBeNull()
        ->and(giftMessageStored($apiCart))->toBeNull();
});

describe('a store that does not allow gift messages on the order', function () {
    it('saves no gift message, and only the API reports it, since the checkout step goes on', function () {
        Mage::app()->getStore()->setConfig(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER, '0');
        [$webCart, $apiCart] = $this->carts;

        giftMessageViaWeb($webCart, ["quote_{$webCart->getId()}" => ['type' => 'quote', 'from' => 'Ann', 'to' => 'Bob', 'message' => 'Happy birthday']]);
        $api = giftMessageViaApi($apiCart, ['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Happy birthday']);

        expect($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Gift messages are not available for this cart'])
            ->and(giftMessageStored($webCart))->toBeNull()
            ->and(giftMessageStored($apiCart))->toBeNull();
    });
});

it('skips the message of an item that is not in the cart of the checkout', function () {
    [$webCart, $otherCart] = $this->carts;
    $otherItemId = (int) $otherCart->getItemsCollection()->getFirstItem()->getId();

    giftMessageViaWeb($webCart, ["item_{$otherItemId}" => ['type' => 'quote_item', 'from' => 'Ann', 'to' => 'Bob', 'message' => 'Hi']]);

    expect((int) Mage::getModel('sales/quote_item')->load($otherItemId)->getGiftMessageId())->toBe(0);
});

describe('the admin order page', function () {
    /**
     * Post the gift message fields of the admin order page for $quote, as its form names them.
     *
     * @param array<int, array<string, string>> $giftMessages
     */
    function giftMessageViaAdmin(Mage_Sales_Model_Quote $quote, array $giftMessages): void
    {
        $save = new class extends Mage_Adminhtml_Model_Giftmessage_Save {
            public ?Mage_Sales_Model_Quote $quote = null;

            #[\Override]
            protected function _getQuote()
            {
                return $this->quote;
            }
        };
        $save->quote = $quote;
        $save->setGiftmessages($giftMessages)->saveAllInQuote();
    }

    it('trims the sender and the recipient, as the checkout and the API do', function () {
        [$adminCart, $apiCart] = $this->carts;

        giftMessageViaAdmin($adminCart, [(int) $adminCart->getId() => ['type' => 'main', 'sender' => ' Ann ', 'recipient' => 'Bob ', 'message' => 'Happy birthday']]);
        giftMessageViaApi($apiCart, ['sender' => ' Ann ', 'recipient' => 'Bob ', 'message' => 'Happy birthday']);

        expect(giftMessageStored($adminCart))->toBe(['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Happy birthday'])
            ->and(giftMessageStored($adminCart))->toBe(giftMessageStored($apiCart));
    });

    it('saves no gift message when the store does not allow gift messages on the order', function () {
        Mage::app()->getStore()->setConfig(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER, '0');
        [$adminCart] = $this->carts;

        giftMessageViaAdmin($adminCart, [(int) $adminCart->getId() => ['type' => 'main', 'sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Happy birthday']]);

        expect((int) $adminCart->getGiftMessageId())->toBe(0)
            ->and(giftMessageStored($adminCart))->toBeNull();
    });
});
