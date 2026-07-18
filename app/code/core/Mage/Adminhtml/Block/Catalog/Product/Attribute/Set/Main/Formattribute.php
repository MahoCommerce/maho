<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Main_Formattribute extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('set_fieldset', ['legend' => Mage::helper('catalog')->__('Add New Attribute')]);

        $fieldset->addField(
            'new_attribute',
            'text',
            [
                'label' => Mage::helper('catalog')->__('Name'),
                'name' => 'new_attribute',
                'required' => true,
            ],
        );

        $fieldset->addField(
            'submit',
            'note',
            [
                'text' => $this->getLayout()->createBlock('adminhtml/widget_button')
                            ->setData([
                                'label'     => Mage::helper('catalog')->__('Add Attribute'),
                                'onclick'   => 'this.form.submit();',
                                'class' => 'add',
                            ])
                            ->toHtml(),
            ],
        );

        $form->setUseContainer(true);
        $form->setMethod('post');
        $this->setForm($form);
        return $this;
    }
}
