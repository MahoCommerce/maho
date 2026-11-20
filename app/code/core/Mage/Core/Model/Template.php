<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

abstract class Mage_Core_Model_Template extends Mage_Core_Model_Abstract
{
    /**
     * Types of template
     */
    public const TYPE_TEXT = 1;
    public const TYPE_HTML = 2;

    /**
     * Default design area for emulation
     */
    public const DEFAULT_DESIGN_AREA = Mage_Core_Model_App_Area::AREA_FRONTEND;

    /**
     * Configuration of design package for template
     *
     * @var \Maho\DataObject|null
     */
    protected $_designConfig;

    /**
     * Configuration of emulated design package.
     *
     * @var \Maho\DataObject|false
     */
    protected $_emulatedDesignConfig = false;

    /**
     * The environment that emulateDesign() replaced.
     */
    private ?\Maho\DataObject $emulatedEnvironment = null;

    /**
     * Run $callback in the store and the area of the design config. Then put back the environment of the caller.
     *
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    protected function withDesignConfig(\Closure $callback): mixed
    {
        $storeId = $this->getDesignConfigStoreId();
        if ($storeId === null || $storeId === (int) Mage::app()->getStore()->getId()) {
            return $callback();
        }
        return Mage::app()->emulate($storeId, $callback, $this->getDesignConfig()->getArea() ?: self::DEFAULT_DESIGN_AREA);
    }

    private function getDesignConfigStoreId(): ?int
    {
        $store = $this->getDesignConfig()->getStore();
        $storeId = is_object($store) ? $store->getId() : $store;
        return $storeId === null || $storeId === '' ? null : (int) $storeId;
    }

    /**
     * Get design configuration data
     *
     * @return \Maho\DataObject
     */
    protected function getDesignConfig()
    {
        if (is_null($this->_designConfig)) {
            $store = Mage::getDesign()->getStore();
            $storeId = is_object($store) ? $store->getId() : $store;
            $this->_designConfig = new \Maho\DataObject([
                'area' => Mage::getDesign()->getArea(),
                'store' => $storeId,
            ]);
        }
        return $this->_designConfig;
    }

    /**
     * Initialize design information for template processing
     *
     * @return  $this
     */
    public function setDesignConfig(array $config)
    {
        $this->getDesignConfig()->setData($config);
        return $this;
    }

    /**
     * Save current design config and replace with design config from specified store
     * Event is not dispatched.
     *
     * @param int|string $storeId
     * @param string $area
     */
    #[\Deprecated(message: 'call setDesignConfig() and render in Mage_Core_Model_App::emulate()', since: '27.1')]
    public function emulateDesign($storeId, $area = self::DEFAULT_DESIGN_AREA): void
    {
        if ($storeId) {
            // save current design settings
            $this->_emulatedDesignConfig = clone $this->getDesignConfig();
            if ($this->getDesignConfig()->getStore() != $storeId) {
                $this->setDesignConfig(['area' => $area, 'store' => $storeId]);
                if ((int) $storeId !== (int) Mage::app()->getStore()->getId()) {
                    $this->emulatedEnvironment = Mage::getSingleton('core/app_emulation')->startEnvironmentEmulation($storeId, $area);
                }
            }
        } else {
            $this->_emulatedDesignConfig = false;
        }
    }

    /**
     * Revert to last design config, used before emulation
     */
    #[\Deprecated(message: 'call setDesignConfig() and render in Mage_Core_Model_App::emulate()', since: '27.1')]
    public function revertDesign(): void
    {
        if ($this->_emulatedDesignConfig) {
            $this->setDesignConfig($this->_emulatedDesignConfig->getData());
            if ($this->emulatedEnvironment !== null) {
                Mage::getSingleton('core/app_emulation')->stopEnvironmentEmulation($this->emulatedEnvironment);
                $this->emulatedEnvironment = null;
            }
            $this->_emulatedDesignConfig = false;
        }
    }

    /**
     * Return true if template type eq text
     *
     * @return bool
     */
    public function isPlain()
    {
        return $this->getType() == self::TYPE_TEXT;
    }

    /**
     * Getter for template type
     *
     * @return int|string
     */
    abstract public function getType();
}
