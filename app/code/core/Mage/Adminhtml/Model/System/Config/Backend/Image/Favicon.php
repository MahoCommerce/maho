<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Backend_Image_Favicon extends Mage_Adminhtml_Model_System_Config_Backend_Image
{
    /**
     * The tail part of directory path for uploading
     */
    public const UPLOAD_DIR = 'favicon';

    /**
     * Return path to directory for upload file
     *
     * @return string
     * @throw Mage_Core_Exception
     */
    #[\Override]
    protected function _getUploadDir()
    {
        $uploadDir = $this->_appendScopeInfo(self::UPLOAD_DIR);
        $uploadRoot = Mage::getBaseDir('media');
        $uploadDir = $uploadRoot . '/' . $uploadDir;
        return $uploadDir;
    }

    /**
     * Makes a decision about whether to add info about the scope.
     *
     * @return bool
     */
    #[\Override]
    protected function _addWhetherScopeInfo()
    {
        return true;
    }

    #[\Override]
    protected function _getAllowedExtensions(): array
    {
        return ['ico', 'png', 'gif', 'jpg', 'jpeg', 'apng', 'webp', 'avif', 'svg'];
    }
}
