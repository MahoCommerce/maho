<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
