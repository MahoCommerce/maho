<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_App_Emulation
{
    /**
     * @var Mage_Core_Model_App
     */
    protected $_app;

    public function __construct(array $args = [])
    {
        $this->_app = empty($args['app']) ? Mage::app() : $args['app'];
    }

    /**
     * Run $callback in the store $storeId, with the design, the locale and the translations of that store. The admin
     * store runs in the admin area, any other store in the frontend area. Then put back the environment of the
     * caller, also when $callback throws. When the request already runs in $storeId, and its translator is loaded for
     * that store and area, skip the switch: the locale of the caller stays, so an admin page that emulates the admin
     * store keeps the interface locale of the admin user.
     *
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    public function emulate(int $storeId, \Closure $callback): mixed
    {
        $area = $storeId === Mage_Core_Model_App::ADMIN_STORE_ID
            ? Mage_Core_Model_App_Area::AREA_ADMINHTML
            : Mage_Core_Model_App_Area::AREA_FRONTEND;
        $environment = Mage_Core_Model_App_Environment::capture($this->_app);
        try {
            if (!$this->runsIn($storeId, $area)) {
                $this->switchTo($storeId, $area);
            }
            return $callback();
        } finally {
            $environment->restore();
        }
    }

    /**
     * Tell if the request runs in the store $storeId and the area $area, with its translator loaded for them
     */
    private function runsIn(int $storeId, string $area): bool
    {
        $translator = $this->_app->getTranslator();
        return $storeId === (int) $this->_app->getStore()->getId()
            && $area === Mage::getDesign()->getArea()
            && $area === $translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA)
            && $storeId === (int) $translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_STORE);
    }

    /**
     * Switch to the environment of the store $storeId. Pass the result to stopEnvironmentEmulation() to put back the
     * environment of the caller.
     *
     * @param int|string|Mage_Core_Model_Store $storeId
     * @param string|null $area
     */
    #[\Deprecated(message: 'use Mage_Core_Model_App::emulate()', since: '27.1')]
    public function startEnvironmentEmulation($storeId, $area = Mage_Core_Model_App_Area::AREA_FRONTEND): \Maho\DataObject
    {
        $storeId = (int) $this->_app->getStore($storeId)->getId();
        return new \Maho\DataObject(['environment' => $this->start($storeId, $area ?? Mage_Core_Model_App_Area::AREA_FRONTEND)]);
    }

    /**
     * @param \Maho\DataObject $initialEnvironmentInfo the result of startEnvironmentEmulation()
     * @return $this
     */
    #[\Deprecated(message: 'use Mage_Core_Model_App::emulate()', since: '27.1')]
    public function stopEnvironmentEmulation(\Maho\DataObject $initialEnvironmentInfo)
    {
        $environment = $initialEnvironmentInfo->getData('environment');
        if (!$environment instanceof Mage_Core_Model_App_Environment) {
            throw new \InvalidArgumentException('Pass the result of startEnvironmentEmulation() to stopEnvironmentEmulation().');
        }
        $environment->restore();
        return $this;
    }

    /**
     * Switch to the environment of the store $storeId and return the environment of the caller.
     */
    protected function start(int $storeId, string $area): Mage_Core_Model_App_Environment
    {
        $environment = Mage_Core_Model_App_Environment::capture($this->_app);
        try {
            $this->switchTo($storeId, $area);
        } catch (\Throwable $e) {
            $environment->restore();
            throw $e;
        }
        return $environment;
    }

    /**
     * Switch to the design, the store, the locale and the translations of the store $storeId for $area
     */
    private function switchTo(int $storeId, string $area): void
    {
        $this->_emulateDesign($storeId, $area);
        $this->_app->setCurrentStore($storeId);
        $this->_emulateLocale($storeId);
        $this->_app->getTranslator()->init($area);
    }

    /**
     * Apply the design of the store $storeId for $area. The admin area uses the package and the themes of the admin
     * store, as an admin request does.
     */
    protected function _emulateDesign(int $storeId, string $area): void
    {
        $design = Mage::getDesign()->setStore($storeId);
        if ($area === Mage_Core_Model_App_Area::AREA_ADMINHTML) {
            $design->applyAdminDesign();
            return;
        }
        $design->setArea($area)->setTheme('');
        $design->setPackageName('');
    }

    /**
     * Apply the locale of the store $storeId.
     */
    protected function _emulateLocale(int $storeId): void
    {
        $localeCode = (string) $this->_getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
        if ($this->_app->getLocale()->getLocaleCode() !== $localeCode) {
            $this->_app->getLocale()->setLocaleCode($localeCode);
            $this->_app->getTranslator()->setLocale($localeCode);
        }
    }

    /**
     * Retrieve config value for store by path
     *
     * @param string $path
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @return mixed
     */
    protected function _getStoreConfig($path, $store = null)
    {
        return Mage::getStoreConfig($path, $store);
    }
}
