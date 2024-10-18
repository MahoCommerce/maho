<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Toolbar_Add extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('eav/attribute/set/toolbar/add.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label' => Mage::helper('eav')->__('Save Attribute Set'),
                    'onclick' => 'if (addSet.submit()) disableElements(\'save\');',
                    'class' => 'save'
                ])
        );
        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label' => Mage::helper('eav')->__('Back'),
                    'onclick' => 'setLocation(\'' . $this->getUrl('*/*/') . '\')',
                    'class' => 'back'
                ])
        );

        $this->setChild(
            'setForm',
            $this->getLayout()->createBlock('eav/adminhtml_attribute_set_main_formset')
        );
        return parent::_prepareLayout();
    }

    protected function _getHeader()
    {
        return Mage::helper('eav')->__('Add New Attribute Set');
    }

    protected function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    protected function getBackButtonHtml()
    {
        return $this->getChildHtml('back_button');
    }

    protected function getFormHtml()
    {
        return $this->getChildHtml('setForm');
    }

    protected function getFormId()
    {
        return $this->getChild('setForm')->getForm()->getId();
    }
}
