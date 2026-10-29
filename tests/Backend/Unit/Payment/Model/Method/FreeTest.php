<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function freeMethodOrderPayment(float $grandTotal): Mage_Sales_Model_Order_Payment
{
    $order = new class extends Mage_Sales_Model_Order {
        #[\Override]
        public function getBillingAddress(): ?Mage_Sales_Model_Order_Address
        {
            return Mage::getModel('sales/order_address')->setCountryId('US');
        }
    };
    $order->setGrandTotal($grandTotal);

    return Mage::getModel('sales/order_payment')->setOrder($order);
}

function freeMethodQuotePayment(float $grandTotal): Mage_Sales_Model_Quote_Payment
{
    $quote = new class extends Mage_Sales_Model_Quote {
        #[\Override]
        public function getBillingAddress(): Mage_Sales_Model_Quote_Address
        {
            return Mage::getModel('sales/quote_address')->setCountryId('US');
        }
    };
    $quote->setGrandTotal($grandTotal);

    return Mage::getModel('sales/quote_payment')->setQuote($quote);
}

/**
 * GHSA-rmw8-7h79-p386: the free method is selected while the cart total is 0. The cart total
 * then goes up, and the order is placed without a new import of the payment data.
 */
it('refuses a payment that has something to pay', function (string $type, float $grandTotal, bool $refused): void {
    $payment = $type === 'order' ? freeMethodOrderPayment($grandTotal) : freeMethodQuotePayment($grandTotal);
    $method = Mage::getModel('payment/method_free')->setInfoInstance($payment);

    if ($refused) {
        expect(fn() => $method->validate())->toThrow(Mage_Core_Exception::class, 'The requested Payment Method is not available.');
    } else {
        expect($method->validate())->toBe($method);
    }
})->with([
    'order with zero total' => ['order', 0.0, false],
    'order that rounds to zero' => ['order', 0.001, false],
    'order with one cent' => ['order', 0.01, true],
    'order at full price' => ['order', 149.99, true],
    'quote with zero total' => ['quote', 0.0, false],
    'quote at full price' => ['quote', 149.99, true],
]);
