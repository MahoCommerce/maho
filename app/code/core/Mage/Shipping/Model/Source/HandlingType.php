<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

class Mage_Shipping_Model_Source_HandlingType
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_Shipping_Model_Carrier_Abstract::HANDLING_TYPE_FIXED, 'label' => Mage::helper('shipping')->__('Fixed')],
            ['value' => Mage_Shipping_Model_Carrier_Abstract::HANDLING_TYPE_PERCENT, 'label' => Mage::helper('shipping')->__('Percent')],
        ];
    }
}
