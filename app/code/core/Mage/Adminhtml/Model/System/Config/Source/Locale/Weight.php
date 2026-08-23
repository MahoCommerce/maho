<?php

/**
 * Weight units a store can declare (general/locale/weight_unit).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Locale_Weight
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'lbs', 'label' => Mage::helper('adminhtml')->__('Pounds (lbs)')],
            ['value' => 'kgs', 'label' => Mage::helper('adminhtml')->__('Kilograms (kg)')],
        ];
    }
}
