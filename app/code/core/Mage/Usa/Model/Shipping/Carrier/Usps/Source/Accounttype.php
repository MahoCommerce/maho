<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Usps_Source_Accounttype
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => Mage::helper('usa')->__('-- Please Select --')],
            ['value' => 'EPS', 'label' => Mage::helper('usa')->__('EPS (Enterprise Payment System)')],
            ['value' => 'PERMIT', 'label' => Mage::helper('usa')->__('PERMIT')],
        ];
    }
}
