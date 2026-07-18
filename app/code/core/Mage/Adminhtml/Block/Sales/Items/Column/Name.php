<?php

/**
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Items_Column_Name extends Mage_Adminhtml_Block_Sales_Items_Column_Default
{
    /**
     * Add line breaks and truncate value
     *
     * @param string $value
     * @return array
     */
    public function getFormattedOption($value)
    {
        $remainder = '';
        $value = Mage::helper('core/string')->truncate($value, 55, '', $remainder);
        return [
            'value' => nl2br($value),
            'remainder' => nl2br($remainder),
        ];
    }
}
