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
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Mage_Core_Model_Locale::WEIGHT_UNIT_OPTIONS as $value => $label) {
            $options[] = ['value' => $value, 'label' => Mage::helper('adminhtml')->__($label)];
        }

        return $options;
    }
}
