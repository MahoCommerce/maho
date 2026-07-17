<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Fedex_Source_Freemethod extends Mage_Usa_Model_Shipping_Carrier_Fedex_Source_Method
{
    #[\Override]
    public function toOptionArray(): array
    {
        $arr = parent::toOptionArray();
        array_unshift($arr, ['value' => '', 'label' => Mage::helper('shipping')->__('None')]);
        return $arr;
    }
}
