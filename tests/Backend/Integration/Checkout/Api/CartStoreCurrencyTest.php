<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

uses(Tests\MahoBackendTestCase::class);

/**
 * A cart is served in its own store, not the API context's, so a currency
 * validated against the context store has to be re-applied to the cart's own.
 * The storefront resolves the same disagreement by recollecting the cart
 * against the currency asked for, so this adapts rather than refuses.
 */

describe('Cart store currency', function (): void {

    beforeEach(function (): void {
        requireUsdBaseStore();
        $this->store = setStoreDisplayCurrency('USD', 'USD,EUR');
        Mage::app()->setCurrentStore(1);

        if ((float) $this->store->getBaseCurrency()->getRate('EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $this->quote = createPricedQuote(loadSimplePricedProduct());
    });

    afterEach(function (): void {
        StoreContext::setRequestedCurrencyCode(null);
        if (!empty($this->quote) && $this->quote->getId()) {
            $this->quote->delete();
        }
        resetCurrencyState();
    });

    test('a requested currency reaches the store the cart is actually in', function (): void {
        StoreContext::setRequestedCurrencyCode('EUR');
        // What a request naming a currency but no store looks like: the context
        // resolved a store the cart does not belong to.
        StoreContext::setStore(0);

        $cart = (new CartService())->getCart((int) $this->quote->getId());

        expect($cart)->not->toBeNull();
        expect($cart->getStore()->getCurrentCurrencyCode())->toBe('EUR');
    });

    test('it is refused when that store cannot serve the currency', function (): void {
        setStoreDisplayCurrency('USD', 'USD');
        StoreContext::setRequestedCurrencyCode('EUR');
        StoreContext::setStore(0);

        expect(fn() => (new CartService())->getCart((int) $this->quote->getId()))
            ->toThrow(BadRequestHttpException::class);
    });

    test('a matching store is left alone', function (): void {
        StoreContext::setRequestedCurrencyCode('EUR');
        StoreContext::setStore(1);
        $this->store->setRequestedCurrencyCode('EUR');

        $cart = (new CartService())->getCart((int) $this->quote->getId());

        expect($cart->getStore()->getCurrentCurrencyCode())->toBe('EUR');
    });

});
