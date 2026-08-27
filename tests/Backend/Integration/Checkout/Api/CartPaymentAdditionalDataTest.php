<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1335: additionalData on the set-payment-method API must reach payment
 * methods as top-level keys, because assignData() reads flat keys (as the
 * onepage and SOAP flows deliver them), not an M2-style additional_data array.
 */

/** Set a store-1 config value for one call and restore the previous value. */
function withStoreConfig(string $path, string $value, Closure $callback): void
{
    $store = Mage::app()->getStore(1);
    $previous = $store->getConfig($path);
    $store->setConfig($path, $value);
    try {
        $callback();
    } finally {
        $store->setConfig($path, (string) $previous);
    }
}

describe('set-payment-method additionalData', function (): void {

    it('delivers additionalData to the payment method as top-level keys', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            withStoreConfig('payment/purchaseorder/active', '1', function () use ($quote): void {
                $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
                (new CartService())->setPaymentMethod($loaded, 'purchaseorder', ['po_number' => 'PO-1335']);

                expect($loaded->getPayment()->getMethod())->toBe('purchaseorder')
                    ->and($loaded->getPayment()->getPoNumber())->toBe('PO-1335');
            });
        } finally {
            $quote->delete();
        }
    });

    it('ignores reserved keys in additionalData', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            (new CartService())->setPaymentMethod($loaded, 'checkmo', [
                'method' => 'purchaseorder',
                'checks' => 0,
                'additional_data' => ['x' => 'y'],
            ]);

            expect($loaded->getPayment()->getMethod())->toBe('checkmo')
                ->and($loaded->getPayment()->getData('additional_data'))->not->toBeArray();
        } finally {
            $quote->delete();
        }
    });

    it('rejects a method whose minimum order total exceeds the quote total', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);

        try {
            withStoreConfig('payment/checkmo/min_order_total', '999999', function () use ($quote): void {
                $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

                expect(fn() => (new CartService())->setPaymentMethod($loaded, 'checkmo'))
                    ->toThrow(BadRequestHttpException::class);
            });
        } finally {
            $quote->delete();
        }
    });

});
