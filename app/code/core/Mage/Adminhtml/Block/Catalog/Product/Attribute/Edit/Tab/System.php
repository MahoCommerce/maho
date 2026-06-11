<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit_Tab_System extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('entity_attribute');

        $form = new \Maho\Data\Form();
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('catalog')->__('System Properties')]);

        if ($model->getAttributeId()) {
            $fieldset->addField('attribute_id', 'hidden', [
                'name' => 'attribute_id',
            ]);
        }

        $yesno = [
            [
                'value' => 0,
                'label' => Mage::helper('catalog')->__('No'),
            ],
            [
                'value' => 1,
                'label' => Mage::helper('catalog')->__('Yes'),
            ]];

        $fieldset->addField('backend_type', 'select', [
            'name' => 'backend_type',
            'label' => Mage::helper('catalog')->__('Data Type for Saving in Database'),
            'title' => Mage::helper('catalog')->__('Data Type for Saving in Database'),
            'options' => [
                'text'      => Mage::helper('catalog')->__('Text'),
                'varchar'   => Mage::helper('catalog')->__('Varchar'),
                'static'    => Mage::helper('catalog')->__('Static'),
                'datetime'  => Mage::helper('catalog')->__('Datetime'),
                'decimal'   => Mage::helper('catalog')->__('Decimal'),
                'int'       => Mage::helper('catalog')->__('Integer'),
            ],
        ]);

        $fieldset->addField('is_global', 'select', [
            'name'  => 'is_global',
            'label' => Mage::helper('catalog')->__('Globally Editable'),
            'title' => Mage::helper('catalog')->__('Globally Editable'),
            'values' => $yesno,
        ]);

        $form->setValues($model->getData());

        if ($model->getAttributeId()) {
            $form->getElement('backend_type')->setDisabled(1);
            if ($model->getIsGlobal()) {
                #$form->getElement('is_global')->setDisabled(1);
            }
        } else {
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
