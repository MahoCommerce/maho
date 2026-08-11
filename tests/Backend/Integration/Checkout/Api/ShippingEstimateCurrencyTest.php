<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Mage\Checkout\Api\GraphQL\CartMutationHandler;

uses(Tests\MahoBackendTestCase::class);

/**
 * estimateShippingMethods converts each rate at the store's live currency, so
 * the `currency` it reports beside those amounts has to be the same one, not
 * the code stamped on the quote row when it was last saved.
 */

describe('GraphQL shipping estimate currency', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the reported currency is the one the rate amounts were converted at', function (): void {
        $rate = useEurDisplayCurrency();
        $product = loadSimplePricedProduct();

        $quote = createPricedQuote($product, 2);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');

        setStoreDisplayCurrency('USD', 'USD,EUR');

        $handler = new CartMutationHandler(new CartService(), new CartMapper());
        $result = $handler->handleShippingMethods(['cartId' => (int) $quote->getId()]);
        $methods = $result['availableShippingMethods'];
        expect($methods)->not->toBeEmpty();

        $flatrate = null;
        foreach ($methods as $method) {
            if ($method['carrierCode'] === 'flatrate') {
                $flatrate = $method;
            }
        }
        if ($flatrate === null) {
            test()->markTestSkipped('Flat rate shipping not available in this environment');
        }

        // Amounts converted at live USD, so the label must read USD too.
        $baseRate = (float) $flatrate['amount'];
        expect($flatrate['currency'])->toBe('USD');

        // The injected free-shipping row shares the label.
        foreach ($methods as $method) {
            expect($method['currency'])->toBe('USD');
        }

        expect($baseRate)->toBeGreaterThan(0.0);
    });

});
