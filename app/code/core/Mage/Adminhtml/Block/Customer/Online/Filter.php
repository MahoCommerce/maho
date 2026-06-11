<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Online_Filter extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();

        $form->addField(
            'filter_value',
            'select',
            [
                'name' => 'filter_value',
                'onchange' => 'this.form.submit()',
                'values' => [
                    [
                        'label' => Mage::helper('customer')->__('All'),
                        'value' => '',
                    ],

                    [
                        'label' => Mage::helper('customer')->__('Customers Only'),
                        'value' => 'filterCustomers',
                    ],

                    [
                        'label' => Mage::helper('customer')->__('Visitors Only'),
                        'value' => 'filterGuests',
                    ],
                ],
                'no_span' => true,
            ],
        );

        $form->setUseContainer(true);
        $form->setId('filter_form');
        $form->setMethod('post');

        $this->setForm($form);
        return parent::_prepareForm();
    }
}
