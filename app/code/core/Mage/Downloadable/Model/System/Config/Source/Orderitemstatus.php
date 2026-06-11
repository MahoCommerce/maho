<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Model_System_Config_Source_Orderitemstatus
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Sales_Model_Order_Item::STATUS_PENDING,
                'label' => Mage::helper('downloadable')->__('Pending'),
            ],
            [
                'value' => Mage_Sales_Model_Order_Item::STATUS_INVOICED,
                'label' => Mage::helper('downloadable')->__('Invoiced'),
            ],
        ];
    }
}
