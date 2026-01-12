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
