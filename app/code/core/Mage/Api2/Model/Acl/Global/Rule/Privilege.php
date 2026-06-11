<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

class Mage_Api2_Model_Acl_Global_Rule_Privilege
{
    public static function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Api2_Model_Resource::OPERATION_CREATE,
                'label' => Mage::helper('api2')->__('Create'),
            ],
            [
                'value' => Mage_Api2_Model_Resource::OPERATION_RETRIEVE,
                'label' => Mage::helper('api2')->__('Retrieve'),
            ],
            [
                'value' => Mage_Api2_Model_Resource::OPERATION_UPDATE,
                'label' => Mage::helper('api2')->__('Update'),
            ],
            [
                'value' => Mage_Api2_Model_Resource::OPERATION_DELETE,
                'label' => Mage::helper('api2')->__('Delete'),
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
            Mage_Api2_Model_Resource::OPERATION_CREATE   => Mage::helper('api2')->__('Create'),
            Mage_Api2_Model_Resource::OPERATION_RETRIEVE => Mage::helper('api2')->__('Retrieve'),
            Mage_Api2_Model_Resource::OPERATION_UPDATE   => Mage::helper('api2')->__('Update'),
            Mage_Api2_Model_Resource::OPERATION_DELETE   => Mage::helper('api2')->__('Delete'),
        ];
    }
}
