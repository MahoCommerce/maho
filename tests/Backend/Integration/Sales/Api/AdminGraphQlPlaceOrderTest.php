<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\GraphQL\OrderMutationHandler;
use Mage\Sales\Api\OrderProvider;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The placeOrder mutation of the admin GraphQL endpoint selects the shipping method and the payment method through
 * Mage_Checkout_Service_Cart, and places the order through Mage_Sales_Service_Order::submit() as staff (#1348).
 */

/**
 * @param array<string, mixed> $variables
 * @return array<string, mixed>
 */
function adminGraphQlPlaceOrder(Mage_Sales_Model_Quote $quote, array $variables = []): array
{
    $handler = new OrderMutationHandler(new OrderProvider(AdminApiParity::adminSecurity()));

    return $handler->handlePlaceOrder($variables + ['cartId' => (int) $quote->getId()])['placeOrder'];
}

beforeEach(function () {
    $this->spy = new class extends Mage_Sales_Service_Order {
        /** @var list<string> */
        public array $newOrderEmails = [];

        #[\Override]
        protected function sendNewOrderEmail(Mage_Sales_Model_Order $order): void
        {
            $this->newOrderEmails[] = (string) $order->getIncrementId();
        }
    };
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', $this->spy);
    $this->product = loadSimplePricedProduct();
    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($this->product);
    $this->stock = [$stock, (float) $stock->getQty(), (bool) $stock->getIsInStock()];
    $this->quote = createPlaceableQuote($this->product, 1);
});

afterEach(function () {
    Mage::unregister('_service/sales/order');
    $order = Mage::getModel('sales/order')->load($this->quote->getId(), 'quote_id');
    if ($order->getId()) {
        Mage::register('isSecureArea', true, true);
        $order->delete();
        Mage::unregister('isSecureArea');
    }
    $this->quote->delete();
    [$stock, $qty, $isInStock] = $this->stock;
    $stock->setQty($qty)->setIsInStock($isInStock)->save();
});

it('places the order with the new order email, unless the mutation asks for none', function () {
    $placed = adminGraphQlPlaceOrder($this->quote, ['shippingMethod' => 'flatrate_flatrate', 'paymentMethod' => 'checkmo']);
    $second = createPlaceableQuote($this->product, 1);
    try {
        $silent = adminGraphQlPlaceOrder($second, ['sendConfirmation' => false]);
    } finally {
        Mage::register('isSecureArea', true, true);
        Mage::getModel('sales/order')->load($second->getId(), 'quote_id')->delete();
        Mage::unregister('isSecureArea');
        $second->delete();
    }

    expect($placed['order']['incrementId'])->toBeString()
        ->and($silent['order']['incrementId'])->toBeString()
        ->and($this->spy->newOrderEmails)->toBe([$placed['order']['incrementId']]);
});

it('refuses a shipping method without a rate with the message of the cart service', function () {
    expect(fn() => adminGraphQlPlaceOrder($this->quote, ['shippingMethod' => 'pest_none']))
        ->toThrow(Mage_Core_Exception::class, 'Shipping method is not available for this address');
});
