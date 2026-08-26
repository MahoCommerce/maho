<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Mage\Checkout\Api\GraphQL\CartMutationHandler;
use Maho\ApiPlatform\Service\StoreContext;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for issue #1337: addItem() and collectAndVerifyTotals()
 * switched the app to the quote's store for price calculation and never
 * switched back. An admin-scoped request (X-Store-Code: admin) then reached
 * order placement in storefront scope, so payment methods keying MOTO handling
 * off Mage::app()->getStore()->isAdmin() sent 3DS-required storefront sales.
 */

/** Simulate the admin scope StoreContextListener sets for X-Store-Code: admin. */
function cartApiEnterAdminScope(): int
{
    $previousStoreId = (int) Mage::app()->getStore()->getId();
    StoreContext::setExplicitStore(0);
    return $previousStoreId;
}

function cartApiLeaveScope(int $previousStoreId): void
{
    (new StoreContext())->reset();
    Mage::app()->setCurrentStore($previousStoreId);
}

describe('cart service store scope (issue #1337)', function (): void {

    it('keeps the admin scope across addItem()', function (): void {
        $product = loadSimplePricedProduct();
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        $previousStoreId = cartApiEnterAdminScope();
        try {
            (new CartService())->addItem($quote, $product->getSku(), 1);

            expect((int) Mage::app()->getStore()->getId())->toBe(0)
                ->and(Mage::app()->getStore()->isAdmin())->toBeTrue();
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
        }
    });

    it('keeps the admin scope across getCart()', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        $previousStoreId = cartApiEnterAdminScope();
        try {
            $loaded = (new CartService())->getCart((int) $quote->getId());

            expect($loaded)->not->toBeNull()
                ->and((int) Mage::app()->getStore()->getId())->toBe(0);
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
        }
    });

    it('keeps the admin scope across handleShippingMethods()', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        $previousStoreId = cartApiEnterAdminScope();
        try {
            $handler = new CartMutationHandler(new CartService(), new CartMapper());
            $result = $handler->handleShippingMethods(['cartId' => (int) $quote->getId()]);

            expect($result['availableShippingMethods'])->not->toBeEmpty()
                ->and((int) Mage::app()->getStore()->getId())->toBe(0);
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
        }
    });

    it('still collects item prices in the quote store scope', function (): void {
        $product = loadSimplePricedProduct();
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        $previousStoreId = cartApiEnterAdminScope();
        try {
            (new CartService())->addItem($quote, $product->getSku(), 2);

            expect((float) $quote->getSubtotal())->toBeGreaterThan(0.0);
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
        }
    });
});
