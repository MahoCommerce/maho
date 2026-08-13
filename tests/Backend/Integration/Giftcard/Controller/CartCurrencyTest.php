<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The storefront gift-card widget returns an applied amount and a remaining
 * balance side by side, formatted with one currency symbol. Both have to be in
 * that currency.
 */

describe('Gift card cart widget currency', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the balance is in the same currency as the amount beside it', function (): void {
        $rate = useEurDisplayCurrency();
        $product = loadSimplePricedProduct();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode(Mage::helper('giftcard')->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        // A cart big enough that the card is not fully consumed, saved while the
        // display currency is EUR so the row is stamped EUR.
        $quote = createPricedQuote($product, 10);
        $quote->setGiftcardCodes(Mage::helper('core')->jsonEncode([$giftcard->getCode() => 50.00]));
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');

        // The shopper switches back to USD; the row still says EUR.
        setStoreDisplayCurrency('USD', 'USD,EUR');
        $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load((int) $quote->getId());
        $reloaded->setTotalsCollectedFlag(false);
        $reloaded->collectTotals();

        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/giftcard/cart/checkBalance', 'POST'),
        );
        $controller = new Maho_Giftcard_CartController($request, new Mage_Core_Controller_Response_Http());
        $getData = Closure::bind(
            fn(Mage_Sales_Model_Quote $q) => $this->_getGiftcardData($q),
            $controller,
            Maho_Giftcard_CartController::class,
        );

        $data = $getData($reloaded);
        expect($data['giftcards'] ?? [])->not->toBeEmpty();
        $card = $data['giftcards'][0];

        // The card is worth 50.00 base. Displayed in USD (base) both the applied
        // amount and the remaining balance must read 50.00, not 50.00 alongside
        // its EUR conversion.
        expect((float) $card['amount'])->toEqualWithDelta(50.00, 0.011);
        expect((float) $card['balance'])->toEqualWithDelta(50.00, 0.011);
    });

});
