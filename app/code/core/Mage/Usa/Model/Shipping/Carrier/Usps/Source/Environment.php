<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Usps_Source_Environment
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'production', 'label' => Mage::helper('usa')->__('Production')],
            ['value' => 'test', 'label' => Mage::helper('usa')->__('Test (TEM)')],
        ];
    }
}
