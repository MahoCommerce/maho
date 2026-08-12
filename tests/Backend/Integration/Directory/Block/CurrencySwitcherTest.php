<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The switcher used to resolve the selected code itself, past the store
 * accessor, to keep a rate-less session choice out of the selected option.
 * The accessor answers that way now, so the block delegates; these pin the
 * behaviour the local workaround used to provide.
 */

describe('Currency switcher block', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('it never reports a currency the store has no rate for', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');
        Mage::app()->setCurrentStore(1);

        $block = Mage::app()->getLayout()->createBlock('directory/currency');

        expect($block->getCurrentCurrencyCode())->toBe('USD');
        expect($block->getCurrentCurrencyCode())->toBe($store->getCurrentCurrencyCode());
    });

    test('it offers only the currencies that have a rate', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');
        Mage::app()->setCurrentStore(1);

        $block = Mage::app()->getLayout()->createBlock('directory/currency');

        // Selecting an option the switcher does not list is unreachable, so the
        // listed set and the selected code have to agree.
        expect($store->getAvailableCurrencyCodes(true))->toContain('GBP');
        expect(array_keys($block->getCurrencies()))->not->toContain('GBP');
        expect(array_keys($block->getCurrencies()))->toContain($block->getCurrentCurrencyCode());
    });

});
