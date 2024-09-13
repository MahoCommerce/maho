<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * System config image field backend model for Zend PDF generator
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Backend_Image_Favicon extends Mage_Adminhtml_Model_System_Config_Backend_Image
{
    /**
     * The tail part of directory path for uploading
     *
     */
    public const UPLOAD_DIR = 'favicon';

    /**
     * Token for the root part of directory path for uploading
     *
     */
    public const UPLOAD_ROOT = 'media';

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
        $uploadRoot = $this->_getUploadRoot(self::UPLOAD_ROOT);
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

    /**
     * Getter for allowed extensions of uploaded files.
     *
     * @return array
     */
    #[\Override]
    protected function _getAllowedExtensions()
    {
        return ['ico', 'png', 'gif', 'jpg', 'jpeg', 'apng'];
    }

    /**
     * Get real media dir path
     *
     * @param string $token
     * @return string
     */
    #[\Override]
    protected function _getUploadRoot($token)
    {
        return Mage::getBaseDir($token);
    }
}
