<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Model_Import_Adapter
{
    /**
     * Adapter factory. Checks for availability, loads and create instance of import adapter object.
     *
     * @param string $type Adapter type ('csv', 'xml', 'array' etc.)
     * @param mixed $options OPTIONAL Adapter constructor options
     * @throws Exception
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    public static function factory($type, $options = null)
    {
        if (!is_string($type) || !$type) {
            Mage::throwException(Mage::helper('importexport')->__('Adapter type must be a non empty string'));
        }
        $adapterClass = self::class . '_' . ucfirst(strtolower($type));

        if (!class_exists($adapterClass)) {
            Mage::throwException("'{$type}' adapter type is not supported");
        }
        $adapter = new $adapterClass($options);

        if (!$adapter instanceof Mage_ImportExport_Model_Import_Adapter_Abstract) {
            Mage::throwException(
                Mage::helper('importexport')->__('Adapter must be an instance of Mage_ImportExport_Model_Import_Adapter_Abstract'),
            );
        }
        return $adapter;
    }

    /**
     * Create adapter instance for specified source file.
     *
     * @param string $source Source file path.
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    public static function findAdapterFor($source)
    {
        return self::factory(pathinfo($source, PATHINFO_EXTENSION), $source);
    }

    /**
     * Create adapter instance for array data.
     *
     * @param array $data Source data array
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    public static function createArrayAdapter($data)
    {
        if (!is_array($data)) {
            Mage::throwException(Mage::helper('importexport')->__('Source data must be an array'));
        }

        return self::factory('array', $data);
    }
}
