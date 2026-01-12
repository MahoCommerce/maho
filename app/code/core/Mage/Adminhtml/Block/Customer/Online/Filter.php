<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
