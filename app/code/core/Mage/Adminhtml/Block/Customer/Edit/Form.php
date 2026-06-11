<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */


class Mage_Adminhtml_Block_Customer_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form([
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
            'enctype'   => 'multipart/form-data',
        ]);

        $customer = Mage::registry('current_customer');

        if ($customer->getId()) {
            $form->addField('entity_id', 'hidden', [
                'name' => 'customer_id',
            ]);
            $form->setValues($customer->getData());
        }

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
