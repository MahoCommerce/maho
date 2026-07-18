<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Load extends Mage_Core_Block_Template
{
    #[\Override]
    protected function _toHtml()
    {
        $result = [];
        foreach ($this->getSortedChildren() as $name) {
            if (!$block = $this->getChild($name)) {
                $result[$name] = Mage::helper('sales')->__('Invalid block: %s.', $name);
            } else {
                $result[$name] = $block->toHtml();
            }
        }
        return Mage::helper('core')->jsonEncode($result);
    }
}
