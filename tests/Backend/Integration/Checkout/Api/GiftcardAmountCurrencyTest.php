<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;

uses(Tests\MahoBackendTestCase::class);

/**
 * applyGiftcard() interprets the client's `amount` against a currency, and that
 * has to be the currency the cart API advertises. Reading it from the stamped
 * quote column instead rejects a correctly denominated request once the stamped
 * currency loses its rate.
 */

describe('Gift card amount currency', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('an amount is accepted when the stamped currency has lost its rate', function (): void {
        useEurDisplayCurrency();
        $product = loadSimplePricedProduct();

        $quote = createPricedQuote($product, 10);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode(Mage::helper('giftcard')->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        // Withdraw the rate: the cart API now advertises and serves base USD.
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('directory/currency_rate');
        $savedRate = $write->fetchOne(
            $write->select()->from($table, 'rate')
                ->where('currency_from = ?', 'USD')->where('currency_to = ?', 'EUR'),
        );

        $write->delete($table, ['currency_from = ?' => 'USD', 'currency_to = ?' => 'EUR']);

        try {
            // Re-point the store at EUR now that the rate is gone: this also drops
            // the memoised base_currency model, which still holds the old rates.
            $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
            expect($store->getCurrentCurrency()->getCode())->toBe('USD');
            expect($quote->getQuoteCurrencyCode())->toBe('EUR');

            // 20.00 in the advertised USD, not in the stamped EUR.
            (new CartService())->applyGiftcard($quote, $giftcard->getCode(), 20.00);

            expect($quote->getGiftcardCodes())->not->toBeEmpty();
        } finally {
            if ($savedRate !== false) {
                $write->insert($table, ['currency_from' => 'USD', 'currency_to' => 'EUR', 'rate' => $savedRate]);
            }
            resetCurrencyState();
        }
    });

});
