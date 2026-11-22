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

    it('keeps the locale of the caller when the request already runs in the store and the area', function () use ($startAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        $translator = Mage::app()->getTranslator()->getState();

        $inside = Mage::app()->emulate(Mage_Core_Model_App::ADMIN_STORE_ID, fn(): string => Mage::app()->getLocale()->getLocaleCode());

        expect($inside)->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
    });

    it('puts back what the callback changes when the request already runs in the store', function () use ($otherLocale) {
        Mage::app()->setCurrentStore(1);
        $design = Mage::getDesign()->setStore(1)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
        Mage::app()->getTranslator()->init(Mage_Core_Model_App_Area::AREA_FRONTEND);
        $package = $design->getPackageName();
        $locale = Mage::app()->getLocale()->getLocaleCode();
        $translator = Mage::app()->getTranslator()->getState();

        Mage::app()->emulate(1, function () use ($design, $otherLocale): void {
            $design->setPackageName('emulation_test');
            Mage::app()->getLocale()->setLocaleCode($otherLocale());
            Mage::app()->getTranslator()->setLocale($otherLocale());
        });

        expect($design->getPackageName())->toBe($package)
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($locale)
            ->and(Mage::app()->getTranslator()->getState())->toEqual($translator);
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

    it('runs the admin store in the admin area, with the package of the admin', function () {
        Mage::app()->setCurrentStore(1);
        Mage::getDesign()->setStore(1)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $inside = Mage::app()->emulate(Mage_Core_Model_App::ADMIN_STORE_ID, fn(): array => [
            Mage::getDesign()->getArea(),
            Mage::getDesign()->getPackageName(),
        ]);

        expect($inside)->toBe([
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
            (string) Mage::getConfig()->getNode('stores/admin/design/package/name'),
        ])->and(Mage::getDesign()->getArea())->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND);
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

    it('renders an email template for the admin store in the admin area', function () use ($renderStoreAndArea) {
        Mage::app()->setCurrentStore(1);
        Mage::getDesign()->setStore(1)->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $rendered = $renderStoreAndArea(['area' => Mage_Core_Model_App_Area::AREA_ADMINHTML, 'store' => Mage_Core_Model_App::ADMIN_STORE_ID]);

        expect($rendered)->toBe(Mage_Core_Model_App::ADMIN_STORE_ID . '|' . Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and(Mage::getDesign()->getArea())->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND);
    });

    it('dispatches the event of an email in the store of its design config', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $observer = new class {
            public ?int $storeId = null;

            public function record(): never
            {
                $this->storeId = (int) Mage::app()->getStore()->getId();
                throw new \RuntimeException('Stop before the send');
            }
        };
        Mage::register('_singleton/emulation_test/observer', $observer);
        Mage::getConfig()->setNode('global/events/email_template_dispatch_before/observers/emulation_test/type', 'singleton');
        Mage::getConfig()->setNode('global/events/email_template_dispatch_before/observers/emulation_test/class', 'emulation_test/observer');
        Mage::getConfig()->setNode('global/events/email_template_dispatch_before/observers/emulation_test/method', 'record');

        $template = Mage::getModel('core/email_template')
            ->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => 1])
            ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText('Hello')
            ->setTemplateSubject('Subject')
            ->setSenderName('Store')
            ->setSenderEmail('store@example.com');

        expect(fn() => $template->send('customer@example.com', 'Customer'))->toThrow(\RuntimeException::class, 'Stop before the send')
            ->and($observer->storeId)->toBe(1)
            ->and((int) Mage::app()->getStore()->getId())->toBe(Mage_Core_Model_App::ADMIN_STORE_ID);
    });

    it('keeps startEnvironmentEmulation() and stopEnvironmentEmulation() working', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);

        // The methods are deprecated, and developer mode turns the deprecation into an exception
        withoutDeprecations(function () {
            $emulation = Mage::getModel('core/app_emulation');
            $environment = $emulation->startEnvironmentEmulation(1);
            expect((int) Mage::app()->getStore()->getId())->toBe(1);
            $emulation->stopEnvironmentEmulation($environment);
        });

        $expectAdmin($locale);
    });

    it('keeps emulateDesign() and revertDesign() working', function () use ($startAdmin, $expectAdmin, $otherLocale) {
        $locale = $otherLocale();
        $startAdmin($locale);
        Mage::getDesign()->setStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        withoutDeprecations(function () {
            $template = Mage::getModel('core/email_template');
            $template->emulateDesign(1);
            expect((int) Mage::app()->getStore()->getId())->toBe(1);
            $template->revertDesign();
        });

        $expectAdmin($locale);
    });
});
