<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Core_Model_App::withLocale() runs a callback with the locale of a store. After the callback, the locale
 * and the translator must be the ones of the caller, also when the callback throws.
 */
describe('The locale of a store', function () {
    $otherLocale = fn(): string => Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1) === 'de_DE' ? 'fr_FR' : 'de_DE';

    $startAdmin = function (string $locale): void {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    };

    it('runs the callback with the locale of the store and the area of the translator', function () use ($startAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        $translator = Mage::app()->getTranslator()->getState();

        $inside = Mage::app()->withLocale(1, fn(): array => [
            Mage::app()->getLocale()->getLocaleCode(),
            Mage::app()->getTranslator()->getLocale(),
            Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA),
            (int) Mage::app()->getStore()->getId(),
        ]);

        $storeLocale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1);
        expect($inside)->toBe([$storeLocale, $storeLocale, Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App::ADMIN_STORE_ID])
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });

    it('puts back the locale and the translator when the callback throws an Error', function () use ($startAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        $translator = Mage::app()->getTranslator()->getState();

        expect(fn() => Mage::app()->withLocale(1, fn() => throw new \Error('Format failed')))
            ->toThrow(\Error::class, 'Format failed');

        expect(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });

    it('keeps the translator as it is when the store uses the current locale', function () use ($startAdmin) {
        $startAdmin(Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1));
        $translator = Mage::app()->getTranslator()->getState();

        $inside = Mage::app()->withLocale(1, fn() => Mage::app()->getTranslator()->getState());

        expect($inside)->toEqual($translator);
    });

    it('puts back the area of the translator after the deprecated emulate() and revert()', function () use ($otherLocale) {
        $locale = $otherLocale();
        Mage::app()->setCurrentStore(1);
        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_FRONTEND);
        $translator = Mage::app()->getTranslator()->getState();

        withoutDeprecations(function () {
            Mage::app()->getLocale()->emulate(1);
            Mage::app()->getLocale()->revert();
        });

        expect(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });

    it('formats prices with the locale of the store and puts back the locale of the caller', function () use ($startAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        $translator = Mage::app()->getTranslator()->getState();

        $format = Mage::helper('core')->jsonDecode(Mage::helper('tax')->getPriceFormat(1));
        $pattern = Mage::app()->withLocale(1, fn(): string => Mage::app()->getStore(1)->getCurrentCurrency()->getOutputFormat());

        expect($format)->toHaveKey('pattern', $pattern)
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });
});
