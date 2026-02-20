<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Store_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $saveLabel = $deleteLabel = $deleteUrl = '';
        switch (Mage::registry('store_type')) {
            case 'website':
                $this->_objectId = 'website_id';
                $saveLabel   = Mage::helper('core')->__('Save Website');
                $deleteLabel = Mage::helper('core')->__('Delete Website');
                $deleteUrl   = $this->_getDeleteUrl(Mage::registry('store_type'));
                break;
            case 'group':
                $this->_objectId = 'group_id';
                $saveLabel   = Mage::helper('core')->__('Save Store');
                $deleteLabel = Mage::helper('core')->__('Delete Store');
                $deleteUrl   = $this->_getDeleteUrl(Mage::registry('store_type'));
                break;
            case 'store':
                $this->_objectId = 'store_id';
                $saveLabel   = Mage::helper('core')->__('Save Store View');
                $deleteLabel = Mage::helper('core')->__('Delete Store View');
                $deleteUrl   = $this->_getDeleteUrl(Mage::registry('store_type'));
                break;
        }
        $this->_controller = 'system_store';

        parent::__construct();

        $this->_updateButton('save', 'label', $saveLabel);
        $this->_updateButton('delete', 'label', $deleteLabel);
        $this->_updateButton('delete', 'onclick', Mage::helper('core/js')->getConfirmSetLocationJs($deleteUrl));

        if (!Mage::registry('store_data')->isCanDelete()) {
            $this->_removeButton('delete');
        }
        if (Mage::registry('store_data')->isReadOnly()) {
            $this->_removeButton('save')->_removeButton('reset');
        }
    }

    /**
     * Get Header text
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        $addLabel = $editLabel = '';
        switch (Mage::registry('store_type')) {
            case 'website':
                $editLabel = Mage::helper('core')->__('Edit Website');
                $addLabel  = Mage::helper('core')->__('New Website');
                break;
            case 'group':
                $editLabel = Mage::helper('core')->__('Edit Store');
                $addLabel  = Mage::helper('core')->__('New Store');
                break;
            case 'store':
                $editLabel = Mage::helper('core')->__('Edit Store View');
                $addLabel  = Mage::helper('core')->__('New Store View');
                break;
        }

        return Mage::registry('store_action') == 'add' ? $addLabel : $editLabel;
    }

    /**
     * Create URL depending on backups
     *
     * @param string $storeType
     * @param bool $backupAvailable
     * @return string
     */
    public function _getDeleteUrl($storeType, $backupAvailable = false)
    {
        $storeType = uc_words($storeType);
        if ($backupAvailable) {
            $deleteUrl   = $this->getUrl('*/*/delete' . $storeType, ['item_id' => Mage::registry('store_data')->getId()]);
        } else {
            $deleteUrl   = $this->getUrl(
                '*/*/delete' . $storeType . 'Post',
                [
                    'item_id' => Mage::registry('store_data')->getId(),
                    'form_key' => Mage::getSingleton('core/session')->getFormKey(),
                ],
            );
        }

        return $deleteUrl;
    }
}
