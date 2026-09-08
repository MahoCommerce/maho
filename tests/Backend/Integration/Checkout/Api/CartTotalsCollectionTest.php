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
 * Every cart mutation request used to collect totals at least twice: once when
 * getCart() loaded the quote and again after the mutation. Only the
 * post-mutation collection produces the persisted totals, so the load-time
 * collection is pure waste. These tests pin the collection count per request.
 */

const CART_COLLECT_TIMER = 'DISPATCH EVENT:sales_quote_collect_totals_before';

/** Run $request while counting real totals collections (the flag-guarded no-ops do not dispatch). */
function cartCountTotalsCollections(Closure $request): int
{
    \Maho\Profiler::enable();
    \Maho\Profiler::reset(CART_COLLECT_TIMER);
    try {
        $request();
        return (int) \Maho\Profiler::fetch(CART_COLLECT_TIMER, 'count');
    } finally {
        \Maho\Profiler::disable();
    }
}

describe('cart mutation totals collection count', function (): void {

    it('collects totals only once for an add-to-cart mutation', function (): void {
        $product = loadSimplePricedProduct();
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        $handler = new CartMutationHandler(new CartService(), new CartMapper());
        try {
            $collections = cartCountTotalsCollections(fn() => $handler->handleAddToCart([
                'cartId' => (int) $quote->getId(),
                'sku' => $product->getSku(),
                'qty' => 1,
            ]));

            expect($collections)->toBe(1);
        } finally {
            $quote->delete();
        }
    });

    it('collects totals only once for an update-quantity mutation', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        $itemId = (int) $quote->getAllVisibleItems()[0]->getId();

        $handler = new CartMutationHandler(new CartService(), new CartMapper());
        try {
            $collections = cartCountTotalsCollections(fn() => $handler->handleUpdateQty([
                'cartId' => (int) $quote->getId(),
                'itemId' => $itemId,
                'qty' => 3,
            ]));

            expect($collections)->toBe(1);
        } finally {
            $quote->delete();
        }
    });

    it('collects totals only once for a set-payment-method call', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            $collections = cartCountTotalsCollections(
                fn() => (new CartService())->setPaymentMethod($loaded, 'checkmo'),
            );

            expect($collections)->toBe(1)
                ->and($loaded->getPayment()->getMethod())->toBe('checkmo');
        } finally {
            $quote->delete();
        }
    });

    it('still collects totals for a get-cart query', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        $handler = new CartMutationHandler(new CartService(), new CartMapper());
        try {
            $collections = cartCountTotalsCollections(fn() => $handler->handleGetCart([
                'cartId' => (int) $quote->getId(),
            ]));

            expect($collections)->toBe(1);
        } finally {
            $quote->delete();
        }
    });

});
