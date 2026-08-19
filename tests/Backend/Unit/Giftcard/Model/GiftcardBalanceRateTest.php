<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A gift card is valued through the rate row pointing either way, since imports only write base
 * to allowed rows. XTN is an ISO 4217 "X" code, so the only rate row in play is the one written here.
 */
const GIFTCARD_BALANCE_CURRENCY = 'XTN';

function giftcardInTestCurrency(float $balance): Maho_Giftcard_Model_Giftcard
{
    // Subclassed rather than fixtured: the test is about the lookup, not the card's website
    $giftcard = new class extends Maho_Giftcard_Model_Giftcard {
        #[\Override]
        public function getCurrencyCode(): string
        {
            return GIFTCARD_BALANCE_CURRENCY;
        }
    };

    return $giftcard->setBalance($balance);
}

function giftcardDropTestRate(): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('directory/currency_rate'),
        ['currency_to = ?' => GIFTCARD_BALANCE_CURRENCY],
    );
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

afterEach(function () {
    giftcardDropTestRate();
});

it('values a card through the rate row that points the other way', function () {
    $baseCode = (string) Mage::app()->getBaseCurrencyCode();
    Mage::getModel('directory/currency')->saveRates([$baseCode => [GIFTCARD_BALANCE_CURRENCY => 2.0]]);

    expect(giftcardInTestCurrency(100.0)->getBalance($baseCode))->toBe(50.0);
});

it('refuses a card it cannot value in either direction', function () {
    $card = giftcardInTestCurrency(100.0);

    expect(fn() => $card->getBalance((string) Mage::app()->getBaseCurrencyCode()))
        ->toThrow(Mage_Core_Exception::class);
});

it('reports the balance as it stands when no other currency is asked for', function () {
    expect(giftcardInTestCurrency(100.0)->getBalance())->toBe(100.0);
    expect(giftcardInTestCurrency(100.0)->getBalance(GIFTCARD_BALANCE_CURRENCY))->toBe(100.0);
});

// An unstamped quote names no currency, and the totals collector asks anyway on every render
it('reports the balance as it stands for a caller that names no currency', function () {
    expect(giftcardInTestCurrency(100.0)->getBalanceIn(null))->toBe(100.0);
    expect(giftcardInTestCurrency(100.0)->getBalanceIn(''))->toBe(100.0);
});
