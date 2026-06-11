<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_File_Storage extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Core';

    /**
     * Return storage code - always filesystem
     *
     * @return int
     */
    public function getCurrentStorageCode()
    {
        return Mage_Core_Model_File_Storage::STORAGE_MEDIA_FILE_SYSTEM;
    }

    /**
     * Retrieve file system storage model
     *
     * @return Mage_Core_Model_File_Storage_File
     */
    public function getStorageFileModel()
    {
        return Mage::getSingleton('core/file_storage_file');
    }

    /**
     * Check if storage is internal - always true for filesystem
     *
     * @param  int|null $storage
     * @return bool
     */
    public function isInternalStorage($storage = null)
    {
        return true;
    }

    /**
     * Retrieve storage model - always filesystem
     *
     * @param  int|null $storage
     * @param  array $params
     * @return Mage_Core_Model_File_Storage_File
     */
    public function getStorageModel($storage = null, $params = [])
    {
        return Mage::getSingleton('core/file_storage')->getStorageModel($storage, $params);
    }

    /**
     * Process storage file - no action needed for filesystem storage
     *
     * @param  string $filename
     * @return false
     */
    public function processStorageFile($filename)
    {
        return false;
    }
}
