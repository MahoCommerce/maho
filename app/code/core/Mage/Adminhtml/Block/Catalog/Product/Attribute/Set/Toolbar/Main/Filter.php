<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Toolbar_Main_Filter extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();

        $collection = Mage::getModel('eav/entity_attribute_set')
            ->getResourceCollection()
            ->load()
            ->toOptionArray();

        $form->addField(
            'set_switcher',
            'select',
            [
                'name' => 'set_switcher',
                'required' => true,
                'class' => 'left-col-block',
                'no_span' => true,
                'values' => $collection,
                'onchange' => 'this.form.submit()',
            ],
        );

        $form->setUseContainer(true);
        $form->setMethod('post');
        $this->setForm($form);
        return $this;
    }
}
