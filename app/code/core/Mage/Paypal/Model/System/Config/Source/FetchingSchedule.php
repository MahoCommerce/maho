<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_System_Config_Source_FetchingSchedule
{
    public function toOptionArray(): array
    {
        return [
            1 => Mage::helper('paypal')->__('Daily'),
            3 => Mage::helper('paypal')->__('Every 3 days'),
            7 => Mage::helper('paypal')->__('Every 7 days'),
            10 => Mage::helper('paypal')->__('Every 10 days'),
            14 => Mage::helper('paypal')->__('Every 14 days'),
            30 => Mage::helper('paypal')->__('Every 30 days'),
            40 => Mage::helper('paypal')->__('Every 40 days'),
        ];
    }
}
