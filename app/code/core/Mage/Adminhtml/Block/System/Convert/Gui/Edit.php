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

class Mage_Adminhtml_Block_System_Convert_Gui_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'system_convert_gui';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Profile'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Profile'));
        $this->_addButton('savecontinue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => "document.getElementById('edit_form').action += 'continue/true/'; editForm.submit();",
            'class' => 'save',
        ], -100);
    }

    public function getProfileId()
    {
        return Mage::registry('current_convert_profile')->getId();
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('current_convert_profile')->getId()) {
            return $this->escapeHtml(Mage::registry('current_convert_profile')->getName());
        }
        return Mage::helper('adminhtml')->__('New Profile');
    }
}
