<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Attribute_Data_Boolean extends Mage_Eav_Model_Attribute_Data_Select
{
    /**
     * Return a text for option value
     *
     * @param int $value
     * @return string
     */
    #[\Override]
    protected function _getOptionText($value)
    {
        $text = match ($value) {
            '0' => Mage::helper('eav')->__('No'),
            '1' => Mage::helper('eav')->__('Yes'),
            default => '',
        };
        return $text;
    }
}
