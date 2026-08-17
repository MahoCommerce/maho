<?php

/**
 * Source model for the MerchantReturnPolicy category selector.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Returns_Policy
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'auto', 'label' => Mage::helper('structureddata')->__('Automatic (from Right of Withdrawal settings)')],
            ['value' => 'finite', 'label' => Mage::helper('structureddata')->__('Finite Return Window')],
            ['value' => 'unlimited', 'label' => Mage::helper('structureddata')->__('Unlimited Return Window')],
            ['value' => 'not_permitted', 'label' => Mage::helper('structureddata')->__('Returns Not Permitted')],
            ['value' => 'disabled', 'label' => Mage::helper('structureddata')->__('Do Not Emit')],
        ];
    }
}
