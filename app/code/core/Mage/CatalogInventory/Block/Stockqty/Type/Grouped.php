<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

class Mage_CatalogInventory_Block_Stockqty_Type_Grouped extends Mage_CatalogInventory_Block_Stockqty_Composite
{
    /**
     * Retrieve child products
     *
     * @return array
     */
    #[\Override]
    protected function _getChildProducts()
    {
        /** @var Mage_Catalog_Model_Product_Type_Grouped $productType */
        $productType = $this->_getProduct()->getTypeInstance(true);
        return $productType->getAssociatedProducts($this->_getProduct());
    }
}
