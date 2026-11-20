<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Store emulation loads the translations of the emulated area and locale.
 * When the emulation stops, the translator must return to the area and the locale it had before.
 */
describe('Store emulation restores the translator', function () {
    $startAdmin = function (string $locale): Mage_Core_Model_Translate {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
        Mage::app()->getLocale()->setLocaleCode($locale);

        return Mage::app()->getTranslator()->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_ADMINHTML, true);
    };

    it('returns to the admin area when the admin and the store use the same locale', function () use ($startAdmin) {
        $storeLocale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1);
        $translator = $startAdmin($storeLocale);

        $emulation = Mage::getModel('core/app_emulation');
        $environment = $emulation->startEnvironmentEmulation(1);
        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND);

        $emulation->stopEnvironmentEmulation($environment);

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and($translator->getLocale())->toBe($storeLocale)
            ->and((int) $translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_STORE))->toBe(Mage_Core_Model_App::ADMIN_STORE_ID);
    });

    it('returns to the admin area and locale when the store uses another locale', function () use ($startAdmin) {
        $adminLocale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1) === 'de_DE' ? 'fr_FR' : 'de_DE';
        $translator = $startAdmin($adminLocale);

        $emulation = Mage::getModel('core/app_emulation');
        $environment = $emulation->startEnvironmentEmulation(1);
        expect($translator->getLocale())->not->toBe($adminLocale);

        $emulation->stopEnvironmentEmulation($environment);

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and($translator->getLocale())->toBe($adminLocale)
            ->and(Mage::app()->getLocale()->getLocaleCode())->toBe($adminLocale);
    });

    it('returns to the outer emulation when two emulations nest', function () use ($startAdmin) {
        $translator = $startAdmin(Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1));

        $outer = Mage::getModel('core/app_emulation');
        $outerEnvironment = $outer->startEnvironmentEmulation(1);
        $inner = Mage::getModel('core/app_emulation');
        $inner->stopEnvironmentEmulation($inner->startEnvironmentEmulation(1, Mage_Core_Model_App_Area::AREA_ADMINHTML));

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_FRONTEND)
            ->and((int) $translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_STORE))->toBe(1);

        $outer->stopEnvironmentEmulation($outerEnvironment);

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    });

    it('keeps the translations that were loaded before the emulation', function () use ($startAdmin) {
        $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1);
        $translator = $startAdmin($locale);
        $before = $translator->getData();

        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('core/translate');
        $write = $resource->getConnection('core_write');
        $string = 'Emulation restore marker';
        $write->insert($table, ['string' => $string, 'translate' => 'Reloaded', 'store_id' => 0, 'locale' => $locale, 'crc_string' => crc32($string)]);
        try {
            $emulation = Mage::getModel('core/app_emulation');
            $emulation->stopEnvironmentEmulation($emulation->startEnvironmentEmulation(1));

            expect($translator->getData())->toBe($before);
        } finally {
            $write->delete($table, ['string = ?' => $string]);
        }
    });

    it('leaves a translator that had no area without an area', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
        $translator = Mage::app()->getTranslator();
        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBeNull();

        $emulation = Mage::getModel('core/app_emulation');
        $emulation->stopEnvironmentEmulation($emulation->startEnvironmentEmulation(1));

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBeNull()
            ->and($translator->getData())->toBe([]);
    });

    it('returns to the admin area after a frontend email renders in an admin request', function () use ($startAdmin) {
        $translator = $startAdmin(Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, 1));

        $template = Mage::getModel('core/email_template');
        $template->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => 1]);
        $template->setTemplateText('{{skin url="images/logo_email.gif"}}');
        $template->getProcessedTemplate([]);

        expect($translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA))->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML)
            ->and(Mage::getDesign()->getArea())->toBe(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    });
});
