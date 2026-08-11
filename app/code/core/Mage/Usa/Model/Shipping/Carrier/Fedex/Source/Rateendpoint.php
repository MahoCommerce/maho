<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Fedex_Source_Rateendpoint
{
    public function toOptionArray(): array
    {
        $fedex = Mage::getSingleton('usa/shipping_carrier_fedex');
        $arr = [];
        foreach ($fedex->getCode('rate_endpoint') as $k => $v) {
            $arr[] = ['value' => $k, 'label' => $v];
        }
        return $arr;
    }
}
