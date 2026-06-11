<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Customer_Address_Type
{
    public function toOptionArray(): array
    {
        return [
            Mage_Customer_Model_Address_Abstract::TYPE_BILLING => Mage::helper('adminhtml')->__('Billing Address'),
            Mage_Customer_Model_Address_Abstract::TYPE_SHIPPING => Mage::helper('adminhtml')->__('Shipping Address'),
        ];
    }
}
