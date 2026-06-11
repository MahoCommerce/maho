<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Abstract_Source_Requesttype
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => Mage::helper('shipping')->__('Divide to equal weight (one request)')],
            ['value' => 1, 'label' => Mage::helper('shipping')->__('Use origin weight (few requests)')],
        ];
    }
}
