<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Convert_Profile_Edit_Tab_Edit extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return $this
     */
    public function initForm()
    {
        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('_edit');

        $model = Mage::registry('current_convert_profile');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('General Information'),
            'class' => 'fieldset-wide',
        ]);

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => Mage::helper('adminhtml')->__('Profile Name'),
            'title' => Mage::helper('adminhtml')->__('Profile Name'),
            'required' => true,
        ]);

        $fieldset->addField('actions_xml', 'textarea', [
            'name' => 'actions_xml',
            'label' => Mage::helper('adminhtml')->__('Actions XML'),
            'title' => Mage::helper('adminhtml')->__('Actions XML'),
            'style' => 'height:30em',
            'required' => true,
        ]);

        $form->setValues($model->getData());

        $this->setForm($form);

        return $this;
    }
}
