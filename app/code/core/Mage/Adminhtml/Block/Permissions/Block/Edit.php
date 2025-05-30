<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Permissions_Block_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'block_id';
        $this->_controller = 'permissions_block';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Block'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Block'));
    }

    /**
     * Return text that to be placed to block header
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('permissions_block')->getId()) {
            return Mage::helper('adminhtml')->__("Edit Block '%s'", $this->escapeHtml(Mage::registry('permissions_block')->getBlockName()));
        }
        return Mage::helper('adminhtml')->__('New block');
    }
}
