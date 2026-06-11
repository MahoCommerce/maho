<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

class Mage_Bundle_Model_Source_Option_Type
{
    public const BUNDLE_OPTIONS_TYPES_PATH = 'global/catalog/product/options/bundle/types';

    public function toOptionArray(): array
    {
        $types = [];

        foreach (Mage::getConfig()->getNode(self::BUNDLE_OPTIONS_TYPES_PATH)->children() as $type) {
            $labelPath = self::BUNDLE_OPTIONS_TYPES_PATH . '/' . $type->getName() . '/label';
            $types[] = [
                'label' => (string) Mage::getConfig()->getNode($labelPath),
                'value' => $type->getName(),
            ];
        }

        return $types;
    }
}
