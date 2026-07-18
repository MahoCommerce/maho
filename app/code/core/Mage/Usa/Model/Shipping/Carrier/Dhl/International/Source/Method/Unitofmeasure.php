<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Dhl_International_Source_Method_Unitofmeasure
{
    public function toOptionArray(): array
    {
        $unitArr = Mage::getSingleton('usa/shipping_carrier_dhl_international')->getCode('unit_of_measure');

        $returnArr = [];
        foreach ($unitArr as $key => $val) {
            $returnArr[] = ['value' => $key, 'label' => $val];
        }
        return $returnArr;
    }
}
