<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;

uses(Tests\MahoBackendTestCase::class);

/**
 * The cart API recomputes every amount on read, so the `currency` field has to
 * name the currency they were computed in, not the code stamped on the row.
 * Fixtures live in tests/Pest.php.
 */

describe('Cart API currency label', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the label follows a display currency change, because the amounts do', function (): void {
        $rate = useEurDisplayCurrency();
        $product = loadSimplePricedProduct();
        $basePrice = (float) $product->getFinalPrice();

        $quote = createPricedQuote($product, 2);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');
        expect((float) $quote->getBaseToQuoteRate())->toEqualWithDelta($rate, 0.0001);

        setStoreDisplayCurrency('USD', 'USD,EUR');

        $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load((int) $quote->getId());
        expect($reloaded->getQuoteCurrencyCode())->toBe('EUR');

        $cart = (new CartMapper())->mapQuoteToCart($reloaded, true);

        expect($cart->prices['subtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->items[0]->price)->toEqualWithDelta($basePrice, 0.011);
        expect($cart->currency)->toBe('USD');
    });

    test('the label is the base currency when the display currency has no rate', function (): void {
        $rate = useEurDisplayCurrency();

        $product = loadSimplePricedProduct();
        $basePrice = (float) $product->getFinalPrice();

        $quote = createPricedQuote($product, 2);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');
        expect((float) $quote->getSubtotal())->toEqualWithDelta($basePrice * 2 * $rate, 0.011);

        $store = useNoRateDisplayCurrency('GBP', 'USD,EUR,GBP');

        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load((int) $quote->getId());
        $cart = (new CartMapper())->mapQuoteToCart($reloaded, true);

        expect($cart->prices['subtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->currency)->toBe('USD');
    });

});
