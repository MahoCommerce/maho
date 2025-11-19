<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Bundle_Model_Source_Option_Selection_Price_Type
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => Mage::helper('bundle')->__('Fixed')],
            ['value' => '1', 'label' => Mage::helper('bundle')->__('Percent')],
        ];
    }
}
