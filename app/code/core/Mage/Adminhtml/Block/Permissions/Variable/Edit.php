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

class Mage_Adminhtml_Block_Permissions_Variable_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'variable_id';
        $this->_controller = 'permissions_variable';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Variable'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Variable'));
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('permissions_variable')->getId()) {
            return Mage::helper('adminhtml')->__("Edit Variable '%s'", $this->escapeHtml(Mage::registry('permissions_variable')->getVariableName()));
        }
        return Mage::helper('adminhtml')->__('New Variable');
    }
}
