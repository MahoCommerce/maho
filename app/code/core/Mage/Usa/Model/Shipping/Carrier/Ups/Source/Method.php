<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
