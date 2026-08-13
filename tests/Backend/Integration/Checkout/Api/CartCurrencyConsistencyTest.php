<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for issue #1238: on a store whose display currency differs
 * from the website base currency, every non-base* money field in the cart API
 * response must be in quote currency. Store 1 is switched to EUR display
 * in-memory (base stays USD); the USD→EUR rate is seeded at install.
 * useEurDisplayCurrency() and the fixtures live in tests/Pest.php.
 */

describe('Cart API quote-currency consistency (issue #1238)', function (): void {

    beforeEach(function (): void {
        $this->rate = useEurDisplayCurrency();
        $this->product = loadSimplePricedProduct();
        $this->mapper = new CartMapper();
    });

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('item price is in quote currency and consistent with rowTotal', function (): void {
        $quote = createPricedQuote($this->product, 2);
        $cart = $this->mapper->mapQuoteToCart($quote, false);

        expect($cart->currency)->toBe('EUR');
        expect($cart->items)->not->toBeEmpty();

        $item = $cart->items[0];
        $basePrice = (float) $this->product->getFinalPrice();
        $expectedUnitPrice = round($basePrice * $this->rate, 2);

        expect($item->price)->toEqualWithDelta($expectedUnitPrice, 0.011);

        // Unit price times qty must land on the row total: one currency per response.
        expect($item->price * $item->qty)->toEqualWithDelta($item->rowTotal, 0.011);

        // The quote-level pair must keep its meaning: base* is base, plain is quote.
        expect($cart->prices['baseSubtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->prices['subtotal'])->toEqualWithDelta($expectedUnitPrice * 2, 0.011);
    });

    test('available shipping method price matches the selected shipping amount', function (): void {
        $quote = createPricedQuote($this->product, 2);
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $cart = $this->mapper->mapQuoteToCart($quote, false);

        $flatrate = null;
        foreach ($cart->availableShippingMethods as $method) {
            if ($method['code'] === 'flatrate_flatrate') {
                $flatrate = $method;
            }
        }

        if ($flatrate === null || $cart->prices['shippingAmount'] === null) {
            test()->markTestSkipped('Flat rate shipping not available in this environment');
        }

        // The same method must not change price between the list and selection.
        expect($flatrate['price'])->toEqualWithDelta($cart->prices['shippingAmount'], 0.011);
        expect($cart->selectedShippingMethod['price'])->toEqualWithDelta($cart->prices['shippingAmount'], 0.011);

        // And the quote-currency amount differs from base when the rate does.
        expect($cart->prices['baseShippingAmount'])->not->toBeNull();
        expect($flatrate['price'])->toEqualWithDelta($cart->prices['baseShippingAmount'] * $this->rate, 0.011);
    });

    test('applied gift card balance and amount are in quote currency', function (): void {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode(Mage::helper('giftcard')->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        // A large enough cart that the full balance applies.
        $quote = createPricedQuote($this->product, 10);

        $service = new CartService();
        $service->applyGiftcard($quote, $giftcard->getCode());

        // The stored snapshot is base currency: the total collector owns the format.
        $storedCodes = Mage::helper('core')->jsonDecode($quote->getGiftcardCodes(), true);
        expect((float) $storedCodes[$giftcard->getCode()])->toEqualWithDelta(50.00, 0.011);

        $cart = (new CartMapper())->mapQuoteToCart($quote, false);

        expect($cart->appliedGiftcards)->toHaveCount(1);
        $applied = $cart->appliedGiftcards[0];

        $expectedEur = round(50.00 * $this->rate, 2);

        expect((float) $applied['balance'])->toEqualWithDelta($expectedEur, 0.011);
        expect((float) $applied['appliedAmount'])->toEqualWithDelta($expectedEur, 0.011);
        expect((float) $cart->prices['giftcardAmount'])->toEqualWithDelta($expectedEur, 0.011);

        // appliedAmount must agree with the amount actually subtracted from totals.
        expect((float) $applied['appliedAmount'])->toEqualWithDelta((float) $cart->prices['giftcardAmount'], 0.011);
    });

});
