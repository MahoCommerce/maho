<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cms_MediaLibrary_Content extends Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Content
{
    public function __construct()
    {
        Mage_Adminhtml_Block_Widget_Container::__construct();
        $this->_headerText = $this->helper('cms')->__('Media Library');
        $this->_removeButton('back')->_removeButton('edit');
        
        // Add folder management buttons
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

        // NOTE: No "Insert File" button for Media Library - it's for browsing, not inserting
    }

    public function getFilebrowserSetupObject(): string
    {
        $setupObject = new Varien_Object([
            'newFolderPrompt'                 => $this->helper('cms')->__('New Folder Name:'),
            'deleteFolderConfirmationMessage' => $this->helper('cms')->__('Are you sure you want to delete current folder?'),
            'deleteFileConfirmationMessage'   => $this->helper('cms')->__('Are you sure you want to delete the selected file?'),
            'contentsUrl'     => $this->getContentsUrl(),
            'newFolderUrl'    => $this->getNewfolderUrl(),
            'deleteFolderUrl' => $this->getDeletefolderUrl(),
            'deleteFilesUrl'  => $this->getDeleteFilesUrl(),
            'headerText'      => $this->getHeaderText(),
        ]);

        return Mage::helper('core')->jsonEncode($setupObject);
    }
}