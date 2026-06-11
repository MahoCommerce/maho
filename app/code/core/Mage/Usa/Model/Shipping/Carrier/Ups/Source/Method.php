<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Ups_Source_Method
{
    public function toOptionArray(): array
    {
        $ups = Mage::getSingleton('usa/shipping_carrier_ups');
        $arr = [];

        // necessary after the add of Rest API
        $origins = $ups->getCode('originShipment');
        foreach ($origins as $origin) {
            foreach ($origin as $k => $v) {
                $arr[] = ['value' => $k, 'label' => Mage::helper('usa')->__($v)];
            }
        }

        // old XML API codes
        foreach ($ups->getCode('method') as $k => $v) {
            $arr[] = ['value' => $k, 'label' => Mage::helper('usa')->__($v)];
        }

        return $arr;
    }
}
