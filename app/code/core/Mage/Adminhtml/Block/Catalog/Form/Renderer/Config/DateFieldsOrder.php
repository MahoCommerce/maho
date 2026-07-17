<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Form_Renderer_Config_DateFieldsOrder extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $_options = [
            'd' => Mage::helper('adminhtml')->__('Day'),
            'm' => Mage::helper('adminhtml')->__('Month'),
            'y' => Mage::helper('adminhtml')->__('Year'),
        ];

        $element->setValues($_options)
            ->setClass('select-date')
            ->setName($element->getName() . '[]');
        if ($element->getValue()) {
            $values = explode(',', $element->getValue());
        } else {
            $values = [];
        }

        $_parts = [];
        $_parts[] = $element->setValue($values[0] ?? null)->getElementHtml();
        $_parts[] = $element->setValue($values[1] ?? null)->getElementHtml();
        $_parts[] = $element->setValue($values[2] ?? null)->getElementHtml();

        return implode(' <span>/</span> ', $_parts);
    }
}
