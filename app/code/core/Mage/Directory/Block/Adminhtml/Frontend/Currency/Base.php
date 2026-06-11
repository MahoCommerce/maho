<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Block_Adminhtml_Frontend_Currency_Base extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        if ($this->getRequest()->getParam('website') != '') {
            $priceScope = Mage::app()->getStore()->getConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE);
            if ($priceScope == Mage_Core_Model_Store::PRICE_SCOPE_GLOBAL) {
                return '';
            }
        }
        return parent::render($element);
    }
}
