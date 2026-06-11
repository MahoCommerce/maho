<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tax_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'rule';
        $this->_controller = 'tax_rule';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('tax')->__('Save Rule'));
        $this->_updateButton('delete', 'label', Mage::helper('tax')->__('Delete Rule'));

        $this->_addButton('save_and_continue', [
            'label'     => Mage::helper('tax')->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class' => 'save',
        ], 10);

        $this->_formScripts[] = " function saveAndContinueEdit(){ editForm.submit(document.getElementById('edit_form').action + 'back/edit/') } ";
    }

    /**
     * Get Header text
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('tax_rule')->getId()) {
            return Mage::helper('tax')->__('Edit Rule');
        }
        return Mage::helper('tax')->__('New Rule');
    }
}
