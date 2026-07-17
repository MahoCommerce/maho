<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

/**
 * Bundle Special Price Attribute Block
 *
 * @package    Mage_Bundle
 *
 * @method $this setDisableChild(bool $value)
 */
class Mage_Bundle_Block_Adminhtml_Catalog_Product_Edit_Tab_Attributes_Special extends Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element
{
    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        return '<input id="' . $this->getElement()->getHtmlId() . '" name="' . $this->getElement()->getName()
             . '" value="' . $this->getElement()->getEscapedValue() . '" ' . $this->getElement()->serialize($this->getElement()->getHtmlAttributes()) . '>' . "\n"
             . '<strong>[%]</strong>';
    }
}
