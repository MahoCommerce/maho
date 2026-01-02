<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Env extends Mage_Install_Model_Installer_Abstract
{
    public function __construct() {}

    public function install(): self
    {
        if (!$this->_checkPhpExtensions()) {
            throw new Exception();
        }
        return $this;
    }

    protected function _checkPhpExtensions(): bool
    {
        $res = true;
        $config = Mage::getSingleton('install/config')->getExtensionsForCheck();
        foreach ($config as $extension => $info) {
            if (!empty($info) && is_array($info)) {
                $res = $this->_checkExtension($info) && $res;
            } else {
                $res = $this->_checkExtension($extension) && $res;
            }
        }
        return $res;
    }

    /**
     * @param string|array<string> $extension
     */
    protected function _checkExtension(string|array $extension): bool
    {
        if (is_array($extension)) {
            $oneLoaded = false;
            foreach ($extension as $item) {
                if (extension_loaded($item)) {
                    $oneLoaded = true;
                }
            }

            if (!$oneLoaded) {
                Mage::getSingleton('install/session')->addError(
                    Mage::helper('install')->__('One of PHP Extensions "%s" must be loaded.', implode(',', $extension)),
                );
                return false;
            }
        } elseif (!extension_loaded($extension)) {
            Mage::getSingleton('install/session')->addError(
                Mage::helper('install')->__('PHP extension "%s" must be loaded.', $extension),
            );
            return false;
        } else {
            /*Mage::getSingleton('install/session')->addError(
                Mage::helper('install')->__("PHP Extension '%s' loaded", $extension)
            );*/
        }
        return true;
    }
}
