<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_regionname';

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Region Name'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Region Name'));

        $this->_addButton('saveandcontinue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild('form', $this->getLayout()->createBlock('directory/adminhtml_regionname_edit_form'));
        return parent::_prepareLayout();
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $regionName = Mage::registry('current_region_name');
        if ($regionName && isset($regionName['region_id']) && $regionName['region_id']) {
            return Mage::helper('adminhtml')->__('Edit Region Name');
        } else {
            return Mage::helper('adminhtml')->__('New Region Name');
        }
    }
}
