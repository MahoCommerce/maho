<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Product_Attribute_Backend_Groupprice extends Mage_Catalog_Model_Resource_Product_Attribute_Backend_Groupprice_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_attribute_group_price', 'value_id');
    }

    /**
     * Add is_percent column
     *
     * @param array $columns
     * @return array
     */
    #[\Override]
    protected function _loadPriceDataColumns($columns)
    {
        $columns               = parent::_loadPriceDataColumns($columns);
        $columns['is_percent'] = 'is_percent';
        return $columns;
    }
}
