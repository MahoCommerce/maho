<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Backend_Email_Logo extends Mage_Adminhtml_Model_System_Config_Backend_Image
{
    /**
     * The tail part of directory path for uploading
     */
    public const UPLOAD_DIR = 'email/logo';

    /**
     * Upload max file size in kilobytes
     *
     * @var int
     */
    protected $_maxFileSize = 2048;

    /**
     * Return path to directory for upload file
     *
     * @return string
     */
    #[\Override]
    protected function _getUploadDir()
    {
        $uploadDir = $this->_appendScopeInfo(self::UPLOAD_DIR);
        $uploadRoot = Mage::getBaseDir('media');
        $uploadDir = $uploadRoot . DS . $uploadDir;
        return $uploadDir;
    }

    /**
     * Makes a decision about whether to add info about the scope
     *
     * @return bool
     */
    #[\Override]
    protected function _addWhetherScopeInfo()
    {
        return true;
    }
}
