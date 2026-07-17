<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
