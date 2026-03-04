<?php

/**
 * Maho
 *
 * @package    Maho_ShippingBridge
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ShippingBridge_Model_Source_AuthType
{
    public function toOptionArray(): array
    {
        $helper = Mage::helper('shipping');
        return [
            ['value' => 'none', 'label' => $helper->__('None')],
            ['value' => 'bearer', 'label' => $helper->__('Bearer Token')],
            ['value' => 'custom_header', 'label' => $helper->__('Custom Header')],
        ];
    }
}
