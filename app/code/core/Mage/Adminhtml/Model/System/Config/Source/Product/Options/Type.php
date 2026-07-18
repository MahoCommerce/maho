<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Product_Options_Type
{
    public const PRODUCT_OPTIONS_GROUPS_PATH = 'global/catalog/product/options/custom/groups';

    public function toOptionArray(): array
    {
        $groups = [
            ['value' => '', 'label' => Mage::helper('adminhtml')->__('-- Please select --')],
        ];

        $helper = Mage::helper('catalog');

        foreach (Mage::getConfig()->getNode(self::PRODUCT_OPTIONS_GROUPS_PATH)->children() as $group) {
            $types = [];
            $typesPath = self::PRODUCT_OPTIONS_GROUPS_PATH . '/' . $group->getName() . '/types';
            foreach (Mage::getConfig()->getNode($typesPath)->children() as $type) {
                $labelPath = self::PRODUCT_OPTIONS_GROUPS_PATH . '/' . $group->getName() . '/types/' . $type->getName()
                    . '/label';
                $types[] = [
                    'label' => $helper->__((string) Mage::getConfig()->getNode($labelPath)),
                    'value' => $type->getName(),
                ];
            }

            $labelPath = self::PRODUCT_OPTIONS_GROUPS_PATH . '/' . $group->getName() . '/label';

            $groups[] = [
                'label' => $helper->__((string) Mage::getConfig()->getNode($labelPath)),
                'value' => $types,
            ];
        }

        return $groups;
    }
}
