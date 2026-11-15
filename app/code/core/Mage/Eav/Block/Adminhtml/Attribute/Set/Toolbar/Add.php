<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Set_Toolbar_Add extends Mage_Adminhtml_Block_Template
{
    use Mage_Eav_Block_Adminhtml_EntityTypeUrlTrait;

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
                    'label'     => Mage::helper('eav')->__('Save'),
                    'onclick'   => 'if (addSet.submit()) disableElements(\'save\');',
                    'class' => 'save',
                ]),
        );
        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('eav')->__('Back'),
                    'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
                    'class' => 'back',
                ]),
        );

        $this->setChild(
            'setForm',
            $this->getLayout()->createBlock('eav/adminhtml_attribute_set_edit_formset'),
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    protected function _getHeader()
    {
        return Mage::helper('eav')->__('Add New Attribute Set');
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

    protected function getFormId(): string
    {
        return $this->getChild('setForm')->getForm()->getId();
    }
}
