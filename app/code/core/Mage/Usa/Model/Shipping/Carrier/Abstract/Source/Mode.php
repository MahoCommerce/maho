<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Model_Shipping_Carrier_Abstract_Source_Mode
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => Mage::helper('usa')->__('Development')],
            ['value' => '1', 'label' => Mage::helper('usa')->__('Live')],
        ];
    }
}
