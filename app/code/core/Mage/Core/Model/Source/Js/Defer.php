<?php

/**
 * Maho
 *
 * @package     Mage_Core
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Source_Js_Defer
{
    const MODE_DISABLED = 0;
    const MODE_DEFER_ONLY = 1;
    const MODE_LOAD_ON_INTENT = 2;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_DISABLED, 'label' => Mage::helper('core')->__('Disabled')],
            ['value' => self::MODE_DEFER_ONLY, 'label' => Mage::helper('core')->__('Defer Only')],
            ['value' => self::MODE_LOAD_ON_INTENT, 'label' => Mage::helper('core')->__('Load on Intent')],
        ];
    }
}
