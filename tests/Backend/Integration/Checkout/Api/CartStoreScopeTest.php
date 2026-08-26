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

function cartScopeCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->setFirstname('Merge');
    $customer->setLastname('Scope');
    $customer->setEmail('cart-scope-merge-' . bin2hex(random_bytes(4)) . '@example.com');
    $customer->save();
    return $customer;
}

/** A dedicated product with finite stock, so the storefront qty check can fail deterministically. */
function cartScopeCreateStockLimitedProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('cart-scope-stock', 20.00, data: [
        'tax_class_id' => 0,
        'stock_data' => [
            'qty' => 5,
            'is_in_stock' => 1,
            'use_config_manage_stock' => 0,
            'manage_stock' => 1,
            'use_config_backorders' => 0,
            'backorders' => 0,
        ],
    ]);
}

describe('cart service store scope (issue #1337)', function (): void {

    it('keeps the admin scope across addItem() while pricing in the quote store', function (): void {
        $product = loadSimplePricedProduct();
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        $previousStoreId = cartApiEnterAdminScope();
        try {
            (new CartService())->addItem($quote, $product->getSku(), 2);

            expect((int) Mage::app()->getStore()->getId())->toBe(0)
                ->and(Mage::app()->getStore()->isAdmin())->toBeTrue()
                ->and((float) $quote->getSubtotal())->toBeGreaterThan(0.0);
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

    it('keeps StoreContext in sync with the app scope inside inQuoteStoreScope()', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);

        $previousStoreId = cartApiEnterAdminScope();
        try {
            CartService::inQuoteStoreScope($quote, function (): void {
                expect((int) Mage::app()->getStore()->getId())->toBe(1)
                    ->and(StoreContext::getStoreId())->toBe(1);
            });

            expect((int) Mage::app()->getStore()->getId())->toBe(0)
                ->and(StoreContext::getStoreId())->toBe(0);
        } finally {
            cartApiLeaveScope($previousStoreId);
        }
    });

    it('enforces the quote store stock limits during an admin-scoped addItem()', function (): void {
        $product = cartScopeCreateStockLimitedProduct();
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        $previousStoreId = cartApiEnterAdminScope();
        try {
            // checkQty() passes any qty when the ambient store is admin, so this
            // rejection only happens when addItem() really switched to the quote store
            expect(fn() => (new CartService())->addItem($quote, $product->getSku(), 10))
                ->toThrow(Mage_Core_Exception::class, 'The requested quantity');

            expect((int) Mage::app()->getStore()->getId())->toBe(0);
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
            $product->delete();
        }
    });

    it('merges an admin-scoped guest cart into the customer cart of the guest store', function (): void {
        $product = loadSimplePricedProduct();
        $service = new CartService();

        $customer = cartScopeCreateCustomer();
        // The customer's existing store-1 cart. The merge must land here: a
        // lookup scoped to the caller's store (admin, 0) would miss it and
        // create a fresh cart instead.
        $existing = $service->getCustomerCart((int) $customer->getId());

        $guestCart = createPricedQuote($product);
        $guestCart->setData('masked_quote_id', bin2hex(random_bytes(16)));
        $guestCart->setIsActive(1);
        $guestCart->save();

        $previousStoreId = cartApiEnterAdminScope();
        try {
            $merged = $service->mergeCarts((string) $guestCart->getData('masked_quote_id'), (int) $customer->getId());

            expect((int) $merged->getId())->toBe((int) $existing->getId())
                ->and((int) $merged->getStoreId())->toBe(1)
                ->and((int) Mage::app()->getStore()->getId())->toBe(0);
        } finally {
            cartApiLeaveScope($previousStoreId);
            $guestCart->delete();
            $existing->delete();
            $customer->delete();
        }
    });

    it('keeps the admin scope across handleShippingMethods()', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        $previousStoreId = cartApiEnterAdminScope();
        try {
            $handler = new CartMutationHandler(new CartService(), new CartMapper());
            $result = $handler->handleShippingMethods(['cartId' => (int) $quote->getId()]);

            // A real carrier must have produced a rate; the handler always
            // injects a synthetic freeshipping row, so not-empty proves nothing
            $carriers = array_column($result['availableShippingMethods'], 'carrierCode');
            expect((int) Mage::app()->getStore()->getId())->toBe(0)
                ->and($carriers)->toContain('flatrate');
        } finally {
            cartApiLeaveScope($previousStoreId);
            $quote->delete();
        }
    });

});
