<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Promo_Quote_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Initialize form
     * Add standard buttons
     * Add "Save and Continue" button
     */
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'promo_quote';

        parent::__construct();

        $this->_addButton('save_and_continue_edit', [
            'class'   => 'save',
            'label'   => Mage::helper('salesrule')->__('Save and Continue Edit'),
            'onclick' => 'editForm.submit(document.getElementById(\'edit_form\').action + \'back/edit/\')',
        ], 10);
    }

    /**
     * Getter for form header text
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        $rule = Mage::registry('current_promo_quote_rule');
        if ($rule->getRuleId()) {
            return Mage::helper('salesrule')->__("Edit Rule '%s'", $this->escapeHtml($rule->getName()));
        }
        return Mage::helper('salesrule')->__('New Rule');
    }

    /**
     * Retrieve products JSON
     *
     * @return string
     */
    public function getProductsJson()
    {
        return '{}';
    }
}
