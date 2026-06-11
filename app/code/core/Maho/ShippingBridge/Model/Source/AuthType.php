<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ShippingBridge
 */

declare(strict_types=1);

class Maho_ShippingBridge_Model_Source_AuthType
{
    public function toOptionArray(): array
    {
        $helper = Mage::helper('shippingbridge');
        return [
            ['value' => 'none', 'label' => $helper->__('None')],
            ['value' => 'bearer', 'label' => $helper->__('Bearer Token')],
            ['value' => 'custom_header', 'label' => $helper->__('Custom Header')],
        ];
    }
}
