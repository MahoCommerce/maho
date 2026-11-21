<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Core_Model_App::emulate() runs a callback in another store. After the callback, the store, the design,
 * the locale and the translator must be the ones of the caller, also when the callback throws.
 */
describe('Store emulation puts back the environment of the caller', function () {
    $otherLocale = fn(): string => Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1) === 'de_DE' ? 'fr_FR' : 'de_DE';

    $startAdmin = function (string $locale): void {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_ADMINHTML, true);
    };

    $expectAdmin = function (string $locale): void {
        expect((int) Mage::app()->getStore()->getId())->toBe(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->and(Mage::getDesign()->getArea())->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    };

    it('runs the callback in the emulated store and returns its result', function () use ($startAdmin, $otherLocale) {
        $startAdmin($otherLocale());

        $inside = Mage::app()->emulate(1, fn(): array => [
            (int) Mage::app()->getStore()->getId(),
            Mage::getDesign()->getArea(),
            Mage::app()->getLocale()->getLocaleCode(),
        ]);

        expect($inside)->toBe([1, Mage_Core_Model_App_Area::AREA_FRONTEND, Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1)]);
    });

    it('puts back the environment when the callback throws an Error', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);

        expect(fn() => Mage::app()->emulate(1, fn() => throw new \Error('Render failed')))->toThrow(\Error::class, 'Render failed');

        $expectAdmin($locale);
    });

    it('runs the callback as it is when the request already runs in the store and the area', function () use ($startAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        $translator = Mage::app()->getTranslator()->getState();

        $inside = Mage::app()->emulate(
            Mage_Core_Model_App::ADMIN_STORE_ID,
            fn(): string => Mage::app()->getLocale()->getLocaleCode(),
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
        );

        expect($inside)->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toBe($translator);
    });

    it('loads the translations when the request runs in the store but has none for the area', function () {
        Mage::app()->setCurrentStore(1);
        Mage::getDesign()->setStore(1)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $area = Mage::app()->emulate(1, fn() => Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA));

        expect($area)->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND);
    });

    it('loads the translations of the store when only the current store changed', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
        Mage::app()->getTranslator()->init(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $translatorStore = Mage::app()->withStore(1, fn() => Mage::app()->emulate(
            1,
            fn() => Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_STORE),
        ));

        expect((int) $translatorStore)->toBe(1);
    });

    it('puts back the package and the theme of the caller', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $design = Mage::getDesign();
        $design->setStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND)->setTheme('emulation_test');
        $design->setPackageName('emulation_test');

        $insidePackage = Mage::app()->emulate(1, fn(): string => $design->getPackageName());

        expect($insidePackage)->not->toBe('emulation_test')
            ->and($design->getPackageName())->toBe('emulation_test')
            ->and($design->getTheme('template'))->toBe('emulation_test');
    });

    it('puts back the environment when an email template throws an Error', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);

        $template = Mage::getModel('core/email_template');
        $template->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => 1]);
        $template->setTemplateText('Hello');
        $template->setTemplateFilter(new class extends Mage_Core_Model_Email_Template_Filter {
            #[\Override]
            public function filter($value): never
            {
                throw new \Error('Filter failed');
            }
        });

        expect(fn() => $template->getProcessedTemplate([]))->toThrow(\Error::class, 'Filter failed');

        $expectAdmin($locale);
    });

    $renderStoreAndArea = function (array $designConfig): string {
        $template = Mage::getModel('core/email_template');
        $template->setDesignConfig($designConfig);
        $template->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT);
        $template->setTemplateText('');
        $template->setTemplateFilter(new class extends Mage_Core_Model_Email_Template_Filter {
            #[\Override]
            public function filter($value): string
            {
                return Mage::app()->getStore()->getId() . '|' . Mage::getDesign()->getArea();
            }
        });
        return $template->getProcessedTemplate([]);
    };

    it('renders an email template in the store that its design config names by code', function () use ($startAdmin, $otherLocale, $renderStoreAndArea) {
        $startAdmin($otherLocale());

        $rendered = $renderStoreAndArea(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => Mage::app()->getStore(1)->getCode()]);

        expect($rendered)->toBe('1|' . Mage_Core_Model_App_Area::AREA_FRONTEND);
    });

    it('renders an email template in the area of its design config when the store is the current one', function () use ($renderStoreAndArea) {
        Mage::app()->setCurrentStore(1);
        Mage::getDesign()->setStore(1)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $rendered = $renderStoreAndArea(['area' => Mage_Core_Model_App_Area::AREA_ADMINHTML, 'store' => 1]);

        expect($rendered)->toBe('1|' . Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and(Mage::getDesign()->getArea())->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND);
    });

    it('keeps startEnvironmentEmulation() and stopEnvironmentEmulation() working', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);

        // The methods are deprecated, and developer mode turns the deprecation into an exception
        set_error_handler(fn(): bool => true, E_USER_DEPRECATED);
        try {
            $emulation = Mage::getModel('core/app_emulation');
            $environment = $emulation->startEnvironmentEmulation(1);
            expect((int) Mage::app()->getStore()->getId())->toBe(1);
            $emulation->stopEnvironmentEmulation($environment);
        } finally {
            restore_error_handler();
        }

        $expectAdmin($locale);
    });

    it('keeps emulateDesign() and revertDesign() working', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        Mage::getDesign()->setStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        set_error_handler(fn(): bool => true, E_USER_DEPRECATED);
        try {
            $template = Mage::getModel('core/email_template');
            $template->emulateDesign(1);
            expect((int) Mage::app()->getStore()->getId())->toBe(1);
            $template->revertDesign();
        } finally {
            restore_error_handler();
        }

        $expectAdmin($locale);
    });
});
