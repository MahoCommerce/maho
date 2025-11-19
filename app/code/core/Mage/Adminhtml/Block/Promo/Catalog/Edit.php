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

class Mage_Adminhtml_Block_Promo_Catalog_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Initialize form
     * Add standard buttons
     * Add "Save and Apply" button
     * Add "Save and Continue" button
     */
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'promo_catalog';

        parent::__construct();

        $this->_addButton('save_apply', [
            'class'   => 'save',
            'label'   => Mage::helper('catalogrule')->__('Save and Apply'),
            'onclick' => "document.getElementById('rule_auto_apply').value=1; editForm.submit()",
        ]);

        $this->_addButton('save_and_continue_edit', [
            'class'   => 'save',
            'label'   => Mage::helper('catalogrule')->__('Save and Continue Edit'),
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
        $rule = Mage::registry('current_promo_catalog_rule');
        if ($rule->getRuleId()) {
            return Mage::helper('catalogrule')->__("Edit Rule '%s'", $this->escapeHtml($rule->getName()));
        }
        return Mage::helper('catalogrule')->__('New Rule');
    }
}
