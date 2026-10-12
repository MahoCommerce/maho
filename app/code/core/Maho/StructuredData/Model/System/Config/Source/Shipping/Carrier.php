<?php

/**
 * Source model for the carriers that give the derived schema.org shipping rate.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_System_Config_Source_Shipping_Carrier
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (array_keys(Mage::getSingleton('shipping/config')->getAllCarriers()) as $code) {
            $title = trim((string) Mage::getStoreConfig("carriers/{$code}/title"));
            $options[] = ['value' => (string) $code, 'label' => $title !== '' ? $title : (string) $code];
        }
        return $options;
    }
}
