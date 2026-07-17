<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

class Mage_SalesRule_Model_System_Config_Source_Coupon_Format
{
    public function toOptionArray(): array
    {
        $formatsList = Mage::helper('salesrule/coupon')->getFormatsList();
        $result = [];
        foreach ($formatsList as $formatId => $formatTitle) {
            $result[] = [
                'value' => $formatId,
                'label' => $formatTitle,
            ];
        }

        return $result;
    }
}
