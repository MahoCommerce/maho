<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Log_Level
{
    public function toOptionArray(): array
    {
        $helper = Mage::helper('adminhtml');

        return [
            Mage::LOG_EMERGENCY->value => $helper->__('Emergency'),
            Mage::LOG_ALERT->value     => $helper->__('Alert'),
            Mage::LOG_CRITICAL->value  => $helper->__('Critical'),
            Mage::LOG_ERROR->value     => $helper->__('Error'),
            Mage::LOG_WARNING->value   => $helper->__('Warning'),
            Mage::LOG_NOTICE->value    => $helper->__('Notice'),
            Mage::LOG_INFO->value      => $helper->__('Informational'),
            Mage::LOG_DEBUG->value     => $helper->__('Debug'),
        ];
    }
}
