<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('currency output formats', function () {
    beforeEach(function () {
        $this->locale = Mage::app()->getLocale();
        $this->originalLocaleCode = $this->locale->getLocaleCode();
    });

    afterEach(function () {
        $this->locale->setLocaleCode($this->originalLocaleCode);
    });

    it('resolves the generic currency placeholder to a real symbol', function () {
        $this->locale->setLocaleCode('en_GB');

        expect($this->locale->getCurrencyOutputFormat('GBP'))->toBe('£%s');
        expect($this->locale->getCurrencyOutputFormat('EUR'))->toBe('€%s');
    });

    it('keeps the locale symbol placement', function () {
        $this->locale->setLocaleCode('de_DE');

        expect($this->locale->getCurrencyOutputFormat('EUR'))->toBe("%s\u{a0}€");
    });

    it('never leaves ¤ in a currency model output format', function () {
        foreach (['en_US', 'en_GB', 'de_DE', 'fr_FR', 'ja_JP'] as $localeCode) {
            $this->locale->setLocaleCode($localeCode);
            foreach (['GBP', 'EUR', 'USD', 'JPY'] as $code) {
                $format = Mage::getModel('directory/currency')->load($code)->getOutputFormat();

                expect($format)->not->toContain('¤');
                expect($format)->toContain('%s');
            }
        }
    });

    it('exposes the currency symbol on the currency model', function () {
        $this->locale->setLocaleCode('en_GB');

        expect(Mage::getModel('directory/currency')->load('GBP')->getCurrencySymbol())->toBe('£');
    });

    it('never leaves ¤ in the js price format used by the admin product form', function () {
        foreach (Mage::app()->getStores() as $store) {
            $priceFormat = Mage::helper('core')->jsonDecode(Mage::helper('tax')->getPriceFormat($store));

            expect($priceFormat['pattern'])->not->toContain('¤');
            expect($priceFormat['pattern'])->toContain('%s');
        }
    });
});
