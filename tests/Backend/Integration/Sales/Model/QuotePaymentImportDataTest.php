<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('quote payment importData applicability checks', function (): void {

    it('applies the full checks by default when the caller passes none', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/checkmo/min_order_total', '999999');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

            expect(fn() => $loaded->getPayment()->importData(['method' => 'checkmo']))
                ->toThrow(Mage_Core_Exception::class);
        } finally {
            $quote->delete();
        }
    });

    it('honors an explicit narrower mask from the caller', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/checkmo/min_order_total', '999999');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            $loaded->getPayment()->importData([
                'method' => 'checkmo',
                'checks' => Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY,
            ]);

            expect($loaded->getPayment()->getMethod())->toBe('checkmo');
        } finally {
            $quote->delete();
        }
    });

});
