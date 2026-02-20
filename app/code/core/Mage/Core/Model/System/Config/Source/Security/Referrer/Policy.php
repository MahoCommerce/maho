<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_System_Config_Source_Security_Referrer_Policy
{
    /**
     * Return options array for Referrer Policy
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => Mage::helper('core')->__('Disabled')],
            ['value' => 'no-referrer', 'label' => Mage::helper('core')->__('No Referrer')],
            ['value' => 'no-referrer-when-downgrade', 'label' => Mage::helper('core')->__('No Referrer When Downgrade')],
            ['value' => 'origin', 'label' => Mage::helper('core')->__('Origin Only')],
            ['value' => 'origin-when-cross-origin', 'label' => Mage::helper('core')->__('Origin When Cross-Origin')],
            ['value' => 'same-origin', 'label' => Mage::helper('core')->__('Same Origin')],
            ['value' => 'strict-origin', 'label' => Mage::helper('core')->__('Strict Origin')],
            ['value' => 'strict-origin-when-cross-origin', 'label' => Mage::helper('core')->__('Strict Origin When Cross-Origin')],
            ['value' => 'unsafe-url', 'label' => Mage::helper('core')->__('Unsafe URL (Not Recommended)')],
        ];
    }
}
