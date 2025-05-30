<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Adminhtml_Block_Directory_Country_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'adminhtml';
        $this->_controller = 'directory_country';

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Country'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Country'));

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
        $this->setChild('form', $this->getLayout()->createBlock('adminhtml/directory_country_edit_form'));
        return parent::_prepareLayout();
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $country = Mage::registry('current_country');
        if ($country && $country->getId()) {
            return Mage::helper('adminhtml')->__('Edit Country "%s"', $this->escapeHtml($country->getName()));
        } else {
            return Mage::helper('adminhtml')->__('New Country');
        }
    }
}
