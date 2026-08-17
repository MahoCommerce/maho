<?php

/**
 * Source model for the MerchantReturnPolicy returnMethod selector.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Returns_Method
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'by_mail', 'label' => Mage::helper('structureddata')->__('Return by Mail')],
            ['value' => 'in_store', 'label' => Mage::helper('structureddata')->__('Return in Store')],
            ['value' => 'at_kiosk', 'label' => Mage::helper('structureddata')->__('Return at Kiosk')],
        ];
    }
}
