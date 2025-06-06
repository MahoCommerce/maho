<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Toolbar_Add extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('catalog/product/attribute/set/toolbar/add.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('catalog')->__('Save Attribute Set'),
                    'onclick'   => 'if (addSet.submit()) disableElements(\'save\');',
                    'class' => 'save',
                ]),
        );
        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('catalog')->__('Back'),
                    'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
                    'class' => 'back',
                ]),
        );

        $this->setChild(
            'setForm',
            $this->getLayout()->createBlock('adminhtml/catalog_product_attribute_set_main_formset'),
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    protected function _getHeader()
    {
        return Mage::helper('catalog')->__('Add New Attribute Set');
    }

    /**
     * @return string
     */
    protected function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    /**
     * @return string
     */
    protected function getBackButtonHtml()
    {
        return $this->getChildHtml('back_button');
    }

    /**
     * @return string
     */
    protected function getFormHtml()
    {
        return $this->getChildHtml('setForm');
    }

    protected function getFormId()
    {
        return $this->getChild('setForm')->getForm()->getId();
    }
}
