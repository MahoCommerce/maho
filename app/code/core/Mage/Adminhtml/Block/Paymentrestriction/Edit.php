<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction edit block
 */
class Mage_Adminhtml_Block_Paymentrestriction_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'adminhtml';
        $this->_controller = 'paymentrestriction';

        $this->_updateButton('save', 'label', Mage::helper('payment')->__('Save Restriction'));
        $this->_updateButton('delete', 'label', Mage::helper('payment')->__('Delete Restriction'));

        $this->_addButton('saveandcontinue', [
            'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

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
