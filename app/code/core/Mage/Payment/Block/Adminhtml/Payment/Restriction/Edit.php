<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Block_Adminhtml_Payment_Restriction_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'payment';
        $this->_controller = 'adminhtml_payment_restriction';

        $this->_updateButton('save', 'label', Mage::helper('payment')->__('Save Restriction'));
        $this->_updateButton('delete', 'label', Mage::helper('payment')->__('Delete Restriction'));

        $this->_addButton('saveandcontinue', [
            'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit(document.getElementById('edit_form').action+'back/edit/');
            }
        ";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $restriction = Mage::registry('payment_restriction');
        if ($restriction && $restriction->getId()) {
            return Mage::helper('payment')->__(
                "Edit Restriction '%s'",
                $this->escapeHtml($restriction->getName()),
            );
        }
        return Mage::helper('payment')->__('New Restriction');
    }
}
