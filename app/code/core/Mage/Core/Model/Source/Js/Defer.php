<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Source_Js_Defer
{
    public const MODE_DISABLED = 0;
    public const MODE_DEFER_ONLY = 1;
    public const MODE_LOAD_ON_INTENT = 2;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_DISABLED, 'label' => Mage::helper('core')->__('Disabled')],
            ['value' => self::MODE_DEFER_ONLY, 'label' => Mage::helper('core')->__('Defer Only')],
            ['value' => self::MODE_LOAD_ON_INTENT, 'label' => Mage::helper('core')->__('Load on Intent')],
        ];
    }
}
