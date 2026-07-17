<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_Variable_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     * @throws Exception
     */
    #[\Override]
    protected function _prepareForm()
    {
        $block = Mage::getModel('admin/variable')->load((int) $this->getRequest()->getParam('variable_id'));

        $form = new \Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl(
                '*/*/save',
                [
                    'variable_id' => (int) $this->getRequest()->getParam('variable_id'),
                ],
            ),
            'method' => 'post',
        ]);
        $fieldset = $form->addFieldset(
            'variable_details',
            ['legend' => $this->__('Variable Details')],
        );

        $fieldset->addField('variable_name', 'text', [
            'label' => $this->__('Variable Name'),
            'required' => true,
            'name' => 'variable_name',
        ]);

        $yesno = [
            [
                'value' => 0,
                'label' => $this->__('No'),
            ],
            [
                'value' => 1,
                'label' => $this->__('Yes'),
            ]];

        $fieldset->addField('is_allowed', 'select', [
            'name' => 'is_allowed',
            'label' => $this->__('Is Allowed'),
            'title' => $this->__('Is Allowed'),
            'values' => $yesno,
        ]);

        $form->setUseContainer(true);
        $form->setValues($block->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
