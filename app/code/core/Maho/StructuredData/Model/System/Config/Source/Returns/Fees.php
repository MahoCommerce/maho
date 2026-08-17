<?php

/**
 * Source model for the MerchantReturnPolicy returnFees selector.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Returns_Fees
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'customer_pays', 'label' => Mage::helper('structureddata')->__('Customer Pays Return Shipping')],
            ['value' => 'free_return', 'label' => Mage::helper('structureddata')->__('Free Return')],
        ];
    }
}
