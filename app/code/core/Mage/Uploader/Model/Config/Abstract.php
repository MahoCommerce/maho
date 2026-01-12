<?php

/**
 * Maho
 *
 * @package    Mage_Uploader
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Uploader_Model_Config_Abstract extends \Maho\DataObject
{
    /**
     * Get file helper
     *
     * @return Mage_Uploader_Helper_File
     */
    protected function _getHelper()
    {
        return Mage::helper('uploader/file');
    }

    /**
     * Set/Get attribute wrapper
     * Also set data in cameCase for config values
     *
     * @param string $method
     * @param array $args
     * @return bool|mixed|\Maho\DataObject
     * @throws \Maho\Exception
     */
    #[\Override]
    public function __call($method, $args)
    {
        $key = lcfirst($this->_camelize(substr($method, 3)));
        return match (substr($method, 0, 3)) {
            'get' => $this->getData($key, $args[0] ?? null),
            'set' => $this->setData($key, $args[0] ?? null),
            'uns' => $this->unsetData($key),
            'has' => isset($this->_data[$key]),
            default => throw new \Maho\Exception('Invalid method ' . static::class . '::' . $method . '(' . print_r($args, true) . ')'),
        };
    }
}
