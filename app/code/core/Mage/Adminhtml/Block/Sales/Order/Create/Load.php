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
