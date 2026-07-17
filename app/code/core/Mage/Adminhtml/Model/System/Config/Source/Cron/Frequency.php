<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency
{
    protected static $_options;

    public const CRON_DAILY    = 'D';
    public const CRON_WEEKLY   = 'W';
    public const CRON_MONTHLY  = 'M';

    public function toOptionArray(): array
    {
        if (!self::$_options) {
            self::$_options = [
                [
                    'label' => Mage::helper('cron')->__('Daily'),
                    'value' => self::CRON_DAILY,
                ],
                [
                    'label' => Mage::helper('cron')->__('Weekly'),
                    'value' => self::CRON_WEEKLY,
                ],
                [
                    'label' => Mage::helper('cron')->__('Monthly'),
                    'value' => self::CRON_MONTHLY,
                ],
            ];
        }
        return self::$_options;
    }
}
