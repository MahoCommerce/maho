<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'customersegmentation';
        $this->_controller = 'adminhtml_segment';
        $this->_mode = 'edit';

        $this->_updateButton('save', 'label', Mage::helper('customersegmentation')->__('Save Segment'));
        $this->_updateButton('delete', 'label', Mage::helper('customersegmentation')->__('Delete Segment'));

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
        $segment = Mage::registry('current_customer_segment');
        if ($segment && $segment->getId()) {
            return Mage::helper('customersegmentation')->__("Edit Segment '%s'", $this->escapeHtml($segment->getName()));
        }
        return Mage::helper('customersegmentation')->__('Add Segment');
    }
}
