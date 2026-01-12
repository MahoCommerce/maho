<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Content extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * Block construction
     */
    public function __construct()
    {
        parent::__construct();
        $this->_headerText = $this->helper('cms')->__('Media Storage');
        $this->_removeButton('back')->_removeButton('edit');
        $this->_addButton('newfolder', [
            'class'   => 'save',
            'label'   => $this->helper('cms')->__('Create Folder...'),
            'type'    => 'button',
            'onclick' => 'MediabrowserInstance.newFolder();',
        ]);

        $this->_addButton('delete_folder', [
            'class'   => 'delete no-display',
            'label'   => $this->helper('cms')->__('Delete Folder'),
            'type'    => 'button',
            'onclick' => 'MediabrowserInstance.deleteFolder();',
            'id'      => 'button_delete_folder',
        ]);

        $this->_addButton('delete_files', [
            'class'   => 'delete no-display',
            'label'   => $this->helper('cms')->__('Delete File'),
            'type'    => 'button',
            'onclick' => 'MediabrowserInstance.deleteFiles();',
            'id'      => 'button_delete_files',
        ]);

        $this->_addButton('insert_files', [
            'class'   => 'save no-display',
            'label'   => $this->helper('cms')->__('Insert File'),
            'type'    => 'button',
            'onclick' => 'MediabrowserInstance.insert();',
            'id'      => 'button_insert_files',
        ]);

        $this->_addButton('edit_image', [
            'class'   => 'save no-display',
            'label'   => $this->helper('cms')->__('Edit Image'),
            'type'    => 'button',
            'onclick' => 'MediabrowserInstance.editImage();',
            'id'      => 'button_edit_image',
        ]);
    }

    /**
     * Files action source URL
     *
     * @return string
     */
    public function getContentsUrl()
    {
        return $this->getUrl('*/*/contents', ['type' => $this->getRequest()->getParam('type')]);
    }

    /**
     * Javascript setup object for filebrowser instance
     *
     * @return string
     */
    public function getFilebrowserSetupObject()
    {
        $setupObject = new \Maho\DataObject();

        $setupObject->setData([
            'targetElementId' => $this->getTargetElementId(),
            'indexUrl'        => $this->getMediaLibraryUrl(),
            'contentsUrl'     => $this->getContentsUrl(),
            'onInsertUrl'     => $this->getOnInsertUrl(),
            'newFolderUrl'    => $this->getNewfolderUrl(),
            'deleteFolderUrl' => $this->getDeletefolderUrl(),
            'deleteFilesUrl'  => $this->getDeleteFilesUrl(),
            'editImageUrl'    => $this->getEditImageUrl(),
            'getImageUrl'     => $this->getImageUrl(),
            'headerText'      => $this->getHeaderText(),
            'canInsertImage'  => $this->getCanInsertImage(),
            'imageFileType'   => $this->getConfiguredImageFileType(),
            'imageQuality'    => $this->getConfiguredImageQuality(),
        ]);

        return Mage::helper('core')->jsonEncode($setupObject);
    }

    /**
     * Main Media Library URL
     *
     * @return string
     */
    public function getMediaLibraryUrl()
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * New directory action target URL
     *
     * @return string
     */
    public function getNewfolderUrl()
    {
        return $this->getUrl('*/*/newFolder');
    }

    /**
     * Delete directory action target URL
     *
     * @return string
     */
    protected function getDeletefolderUrl()
    {
        return $this->getUrl('*/*/deleteFolder');
    }

    /**
     * @return string
     */
    public function getDeleteFilesUrl()
    {
        return $this->getUrl('*/*/deleteFiles');
    }

    /**
     * Edit image action target URL
     *
     * @return string
     */
    public function getEditImageUrl()
    {
        return $this->getUrl('*/*/editImage');
    }

    /**
     * Get image URL action
     *
     * @return string
     */
    public function getImageUrl()
    {
        return $this->getUrl('*/*/getImageUrl');
    }

    /**
     * New directory action target URL
     *
     * @return string
     */
    public function getOnInsertUrl()
    {
        return $this->getUrl('*/*/onInsert');
    }

    /**
     * Target element ID getter
     *
     * @return string
     */
    public function getTargetElementId()
    {
        return $this->getRequest()->getParam('target_element_id');
    }

    /**
     * Current alt text value passed from client
     */
    public function getAltText(): string
    {
        $alt = $this->getRequest()->getParam('alt');
        return $alt ? Mage::helper('cms')->urlDecode($alt) : '';
    }

    /**
     * Get configured image file type from system config
     */
    public function getConfiguredImageFileType(): array
    {
        $configuredType = (int) Mage::getStoreConfig('system/media_storage_configuration/image_file_type');

        // Map image type constants to file extensions and MIME types
        $typeMap = [
            IMAGETYPE_AVIF => ['extension' => 'avif', 'mimeType' => 'image/avif', 'label' => 'AVIF'],
            IMAGETYPE_GIF  => ['extension' => 'gif',  'mimeType' => 'image/gif',  'label' => 'GIF'],
            IMAGETYPE_JPEG => ['extension' => 'jpg',  'mimeType' => 'image/jpeg', 'label' => 'JPG'],
            IMAGETYPE_PNG  => ['extension' => 'png',  'mimeType' => 'image/png',  'label' => 'PNG'],
            IMAGETYPE_WEBP => ['extension' => 'webp', 'mimeType' => 'image/webp', 'label' => 'WebP'],
        ];

        return $typeMap[$configuredType] ?? $typeMap[IMAGETYPE_WEBP]; // Default to WebP
    }

    /**
     * Get configured image quality from system config
     */
    public function getConfiguredImageQuality(): float
    {
        $quality = (int) Mage::getStoreConfig('system/media_storage_configuration/image_quality');

        // Convert to 0-1 scale for filerobot-image-editor
        return $quality / 100;
    }
}
