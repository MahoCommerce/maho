<?php

/**
 * Source model for the Organization schema @type selector.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Organization_Type
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'Organization', 'label' => Mage::helper('structureddata')->__('Organization')],
            ['value' => 'OnlineStore', 'label' => Mage::helper('structureddata')->__('Online Store')],
            ['value' => 'LocalBusiness', 'label' => Mage::helper('structureddata')->__('Local Business')],
        ];
    }
}
