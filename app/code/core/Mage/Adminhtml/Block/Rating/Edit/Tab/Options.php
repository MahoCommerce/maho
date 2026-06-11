<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Rating_Edit_Tab_Options extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('options_form', ['legend' => Mage::helper('rating')->__('Assigned Options')]);

        if (Mage::registry('rating_data')) {
            $collection = Mage::getModel('rating/rating_option')
                ->getResourceCollection()
                ->addRatingFilter(Mage::registry('rating_data')->getId())
                ->load();

            $i = 1;
            foreach ($collection->getItems() as $item) {
                $fieldset->addField(
                    'option_code_' . $item->getId(),
                    'text',
                    [
                        'label'     => Mage::helper('rating')->__('Option Label'),
                        'required'  => true,
                        'name'      => 'option_title[' . $item->getId() . ']',
                        'value'     => $item->getCode() ?: $i,
                    ],
                );
                $i++;
            }
        } else {
            for ($i = 1; $i <= 5; $i++) {
                $fieldset->addField(
                    'option_code_' . $i,
                    'text',
                    [
                        'label'     => Mage::helper('rating')->__('Option Title'),
                        'required'  => true,
                        'name'      => 'option_title[add_' . $i . ']',
                        'value'     => $i,
                    ],
                );
            }
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }
}
