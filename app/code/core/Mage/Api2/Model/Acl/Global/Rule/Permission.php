<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

class Mage_Api2_Model_Acl_Global_Rule_Permission
{
    /**
     * Source keys
     */
    public const TYPE_ALLOW = 1;
    public const TYPE_DENY  = 0;

    public static function toOptionArray(): array
    {
        return [
            [
                'value' => self::TYPE_DENY,
                'label' => Mage::helper('api2')->__('Deny'),
            ],
            [
                'value' => self::TYPE_ALLOW,
                'label' => Mage::helper('api2')->__('Allow'),
            ],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public static function toArray()
    {
        return [
            self::TYPE_DENY  => Mage::helper('api2')->__('Deny'),
            self::TYPE_ALLOW => Mage::helper('api2')->__('Allow'),
        ];
    }
}
