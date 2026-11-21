<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * After a switch to the locale of a store, the locale and the translator must be the ones of the caller.
 */
describe('The locale of a store', function () {
    $otherLocale = fn(): string => Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1) === 'de_DE' ? 'fr_FR' : 'de_DE';

    $startAdmin = function (string $locale): void {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    };

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
        $pattern = Mage::app()->emulate(1, fn(): string => Mage::app()->getStore(1)->getCurrentCurrency()->getOutputFormat());

        expect($format)->toHaveKey('pattern', $pattern)
            ->and((int) Mage::app()->getStore()->getId())->toBe(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });
});
