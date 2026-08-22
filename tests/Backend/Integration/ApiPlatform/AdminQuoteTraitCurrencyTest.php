<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AdminQuoteTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

uses(Tests\MahoBackendTestCase::class);

/**
 * placeOrder and the shipping estimate load a cart through AdminQuoteTrait,
 * not CartService::getCart(), so the trait has to perform the same
 * adapt-or-refuse step, or those paths ignore the X-Currency-Code header the
 * cart reads honored and stamp the order in a currency the caller never saw.
 */

describe('Admin quote load currency', function (): void {

    beforeEach(function (): void {
        requireUsdBaseStore();
        $this->store = setStoreDisplayCurrency('USD', 'USD,EUR');
        Mage::app()->setCurrentStore(1);

        if ((float) Mage::helper('directory')->getRate((string) $this->store->getBaseCurrencyCode(), 'EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $this->quote = createPricedQuote(loadSimplePricedProduct());
        $this->loader = new class {
            use AdminQuoteTrait;

            public function load(int $cartId): Mage_Sales_Model_Quote
            {
                return $this->loadAdminQuote($cartId);
            }
        };
    });

    afterEach(function (): void {
        StoreContext::setRequestedCurrencyCode(null);
        if (!empty($this->quote) && $this->quote->getId()) {
            $this->quote->delete();
        }
        resetCurrencyState();
    });

    test('loading a cart re-applies the requested currency to its own store', function (): void {
        StoreContext::setRequestedCurrencyCode('EUR');
        StoreContext::setStore(0);

        $quote = $this->loader->load((int) $this->quote->getId());

        expect($quote->getStore()->getCurrentCurrencyCode())->toBe('EUR');
    });

    test('it is refused when the cart store cannot serve the currency', function (): void {
        setStoreDisplayCurrency('USD', 'USD');
        StoreContext::setRequestedCurrencyCode('EUR');
        StoreContext::setStore(0);

        expect(fn() => $this->loader->load((int) $this->quote->getId()))
            ->toThrow(BadRequestHttpException::class);
    });

});
