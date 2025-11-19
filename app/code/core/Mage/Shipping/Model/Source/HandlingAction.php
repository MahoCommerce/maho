<?php

/**
 * Maho
 *
 * @package    Mage_Shipping
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Shipping_Model_Source_HandlingAction
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_Shipping_Model_Carrier_Abstract::HANDLING_ACTION_PERORDER, 'label' => Mage::helper('shipping')->__('Per Order')],
            ['value' => Mage_Shipping_Model_Carrier_Abstract::HANDLING_ACTION_PERPACKAGE , 'label' => Mage::helper('shipping')->__('Per Package')],
        ];
    }
}
