<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'cataloglinkrule';
        $this->_controller = 'adminhtml_rule';

        $this->_updateButton('save', 'label', Mage::helper('cataloglinkrule')->__('Save Rule'));
        $this->_updateButton('delete', 'label', Mage::helper('cataloglinkrule')->__('Delete Rule'));

        $this->_addButton('saveandcontinue', [
            'label'     => Mage::helper('cataloglinkrule')->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                editForm.submit(document.getElementById('edit_form').action + 'back/edit/');
            }
        ";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        if (Mage::registry('current_linkrule')->getId()) {
            return Mage::helper('cataloglinkrule')->__(
                "Edit Rule '%s'",
                $this->escapeHtml(Mage::registry('current_linkrule')->getName()),
            );
        }
        return Mage::helper('cataloglinkrule')->__('New Rule');
    }
}
