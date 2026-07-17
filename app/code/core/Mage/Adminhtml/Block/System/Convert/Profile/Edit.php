<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Convert_Profile_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'system_convert_profile';

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
