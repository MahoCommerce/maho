<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_App_Emulation extends \Maho\DataObject
{
    /**
     * Factory instance
     *
     * @var Mage_Core_Model_Factory
     */
    protected $_factory;

    /**
     * Application instance
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
        $this->_app = empty($args['app']) ? Mage::app() : $args['app'];
        unset($args['factory'], $args['app']);
        parent::__construct($args);
    }

    /**
     * Start environment emulation of the specified store
     *
     * Function returns information about initial store environment and emulates environment of another store
     *
     * @param int $storeId
     * @param string $area
     *
     * @return \Maho\DataObject information about environment of the initial store
     */
    public function startEnvironmentEmulation(
        $storeId,
        $area = Mage_Core_Model_App_Area::AREA_FRONTEND,
    ) {
        if (is_null($area)) {
            $area = Mage_Core_Model_App_Area::AREA_FRONTEND;
        }
        $initialDesign = $this->_emulateDesign($storeId, $area);
        // Current store needs to be changed right before locale change and after design change
        $this->_app->setCurrentStore($storeId);
        $initialLocaleCode = $this->_emulateLocale($storeId, $area);

        $initialEnvironmentInfo = new \Maho\DataObject();
        $initialEnvironmentInfo->setInitialDesign($initialDesign)
            ->setInitialLocaleCode($initialLocaleCode);

        Mage::app()->getTranslator()->init($area, true);

        return $initialEnvironmentInfo;
    }

    /**
     * Stop enviromment emulation
     *
     * Function restores initial store environment
     *
     * @param \Maho\DataObject $initialEnvironmentInfo information about environment of the initial store
     *
     * @return $this
     */
    public function stopEnvironmentEmulation(\Maho\DataObject $initialEnvironmentInfo)
    {
        $initialDesign = $initialEnvironmentInfo->getInitialDesign();
        $this->_restoreInitialDesign($initialDesign);
        // Current store needs to be changed right before locale change and after design change
        $this->_app->setCurrentStore($initialDesign['store']);
        $this->_restoreInitialLocale($initialEnvironmentInfo->getInitialLocaleCode(), $initialDesign['area']);
        return $this;
    }

    /**
     * Apply design of the specified store
     *
     * @param int $storeId
     * @param string $area
     *
     * @return array initial design parameters(package, store, area)
     */
    protected function _emulateDesign($storeId, $area = Mage_Core_Model_App_Area::AREA_FRONTEND)
    {
        $initialDesign = Mage::getDesign()->setAllGetOld([
            'package' => $this->_getStoreConfig('design/package/name', $storeId),
            'store'   => $storeId,
            'area'    => $area,
        ]);
        Mage::getDesign()->setTheme('');
        Mage::getDesign()->setPackageName('');
        return $initialDesign;
    }

    /**
     * Apply locale of the specified store
     *
     * @param null|string|bool|int|Mage_Core_Model_Store $storeId
     * @param string $area
     *
     * @return string initial locale code
     */
    protected function _emulateLocale($storeId, $area = Mage_Core_Model_App_Area::AREA_FRONTEND)
    {
        $initialLocaleCode = $this->_app->getLocale()->getLocaleCode();
        $newLocaleCode = $this->_getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
        if ($initialLocaleCode != $newLocaleCode) {
            $this->_app->getLocale()->setLocaleCode($newLocaleCode);
            $this->_factory->getSingleton('core/translate')->setLocale($newLocaleCode)->init($area, true);
        }
        return $initialLocaleCode;
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

    /**
     * Restore design of the initial store
     *
     * @return $this
     */
    protected function _restoreInitialDesign(array $initialDesign)
    {
        Mage::getDesign()->setAllGetOld($initialDesign);
        Mage::getDesign()->setTheme('');
        Mage::getDesign()->setPackageName('');
        return $this;
    }

    /**
     * Restore locale of the initial store
     *
     * @param string $initialLocaleCode
     * @param string $initialArea
     *
     * @return $this
     */
    protected function _restoreInitialLocale(
        $initialLocaleCode,
        $initialArea = Mage_Core_Model_App_Area::AREA_ADMINHTML,
    ) {
        $currentLocaleCode = $this->_app->getLocale()->getLocaleCode();
        if ($currentLocaleCode != $initialLocaleCode) {
            $this->_app->getLocale()->setLocaleCode($initialLocaleCode);
            $this->_factory->getSingleton('core/translate')->setLocale($initialLocaleCode)->init($initialArea, true);
        }
        return $this;
    }
}
