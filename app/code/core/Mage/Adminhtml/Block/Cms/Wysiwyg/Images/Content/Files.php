<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Content_Files extends Mage_Adminhtml_Block_Template
{
    /**
     * Files collection object
     *
     * @var \Maho\Data\Collection\Filesystem
     */
    protected $_filesCollection;

    /**
     * Prepared Files collection for current directory
     *
     * @return \Maho\Data\Collection\Filesystem
     */
    public function getFiles()
    {
        if (!$this->_filesCollection) {
            $this->_filesCollection = Mage::getSingleton('cms/wysiwyg_images_storage')->getFilesCollection(Mage::helper('cms/wysiwyg_images')->getCurrentPath(), $this->_getMediaType());
        }

        return $this->_filesCollection;
    }

    /**
     * Files collection count getter
     *
     * @return int
     */
    public function getFilesCount()
    {
        return $this->getFiles()->count();
    }

    /**
     * File idetifier getter
     *
     * @return string
     */
    public function getFileId(\Maho\DataObject $file)
    {
        return $file->getId();
    }

    /**
     * File thumb URL getter
     *
     * @return string
     */
    public function getFileThumbUrl(\Maho\DataObject $file)
    {
        return $file->getThumbUrl();
    }

    /**
     * File name URL getter
     *
     * @return string
     */
    public function getFileName(\Maho\DataObject $file)
    {
        return $file->getName();
    }

    /**
     * Image file width getter
     *
     * @return string
     */
    public function getFileWidth(\Maho\DataObject $file)
    {
        return $file->getWidth();
    }

    /**
     * Image file height getter
     *
     * @return string
     */
    public function getFileHeight(\Maho\DataObject $file)
    {
        return $file->getHeight();
    }

    /**
     * File short name getter
     *
     * @return string
     */
    public function getFileShortName(\Maho\DataObject $file)
    {
        return $file->getShortName();
    }

    public function getImagesWidth()
    {
        return Mage::getSingleton('cms/wysiwyg_images_storage')->getConfigData('resize_width');
    }

    public function getImagesHeight()
    {
        return Mage::getSingleton('cms/wysiwyg_images_storage')->getConfigData('resize_height');
    }

    /**
     * Return current media type based on request or data
     * @return string
     */
    protected function _getMediaType()
    {
        if ($this->hasData('media_type')) {
            return $this->_getData('media_type');
        }
        return $this->getRequest()->getParam('type');
    }
}
