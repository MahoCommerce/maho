<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tax_Class_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();

        $this->setId('taxClassForm');
    }

    #[\Override]
    protected function _prepareForm()
    {
        $model  = Mage::registry('tax_class');
        $form   = new \Maho\Data\Form([
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
        ]);

        $classType  = $this->getClassType();

        $this->setTitle($classType == Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER
            ? Mage::helper('cms')->__('Customer Tax Class Information')
            : Mage::helper('cms')->__('Product Tax Class Information'));

        $fieldset   = $form->addFieldset('base_fieldset', [
            'legend'    => $classType == Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER
                ? Mage::helper('tax')->__('Customer Tax Class Information')
                : Mage::helper('tax')->__('Product Tax Class Information'),
        ]);

        $fieldset->addField(
            'class_name',
            'text',
            [
                'name'  => 'class_name',
                'label' => Mage::helper('tax')->__('Class Name'),
                'class' => 'required-entry',
                'value' => $model->getClassName(),
                'required' => true,
            ],
        );

        $fieldset->addField(
            'class_type',
            'hidden',
            [
                'name'      => 'class_type',
                'value'     => $classType,
                'no_span'   => true,
            ],
        );

        if ($model->getId()) {
            $fieldset->addField(
                'class_id',
                'hidden',
                [
                    'name'      => 'class_id',
                    'value'     => $model->getId(),
                    'no_span'   => true,
                ],
            );
        }

        $form->setAction($this->getUrl('*/tax_class/save'));
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
