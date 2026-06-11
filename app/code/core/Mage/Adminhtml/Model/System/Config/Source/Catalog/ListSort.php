<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_ListSort
{
    public function toOptionArray(): array
    {
        $options = [];
        $options[] = [
            'label' => Mage::helper('catalog')->__('Best Value'),
            'value' => 'position',
        ];
        foreach ($this->_getCatalogConfig()->getAttributesUsedForSortBy() as $attribute) {
            $options[] = [
                'label' => Mage::helper('catalog')->__($attribute['frontend_label']),
                'value' => $attribute['attribute_code'],
            ];
        }
        return $options;
    }

    /**
     * Retrieve Catalog Config Singleton
     *
     * @return Mage_Catalog_Model_Config
     */
    protected function _getCatalogConfig()
    {
        return Mage::getSingleton('catalog/config');
    }
}
