<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Model_Config_Source_Cart_Summary
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => Mage::helper('checkout')->__('Unique products (2 different products = 2)')],
            ['value' => 1, 'label' => Mage::helper('checkout')->__('Total quantity (2 shirts + 3 pants = 5)')],
        ];
    }
}
