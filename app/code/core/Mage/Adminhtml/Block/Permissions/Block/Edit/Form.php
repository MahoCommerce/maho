<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_Block_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     * @throws Exception
     */
    #[\Override]
    protected function _prepareForm()
    {
        $block = Mage::getModel('admin/block')->load((int) $this->getRequest()->getParam('block_id'));

        $form = new \Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['block_id' => (int) $this->getRequest()->getParam('block_id')]),
            'method' => 'post',
        ]);
        $fieldset = $form->addFieldset(
            'block_details',
            ['legend' => $this->__('Block Details')],
        );

        $fieldset->addField('block_name', 'text', [
            'label' => $this->__('Block Name'),
            'required' => true,
            'name' => 'block_name',
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
