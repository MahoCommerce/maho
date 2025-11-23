<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Model_Shipping_Carrier_Usps_Source_Environment
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'production', 'label' => Mage::helper('usa')->__('Production')],
            ['value' => 'test', 'label' => Mage::helper('usa')->__('Test (TEM)')],
        ];
    }
}
