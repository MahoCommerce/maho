<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Index
{
    /**
     * Rebuild indexes
     * @return $this
     */
    public function rebuild()
    {
        Mage::getResourceSingleton('catalog/category_indexer_product')->reindexAll();
        foreach (Mage::app()->getStores() as $store) {
            Mage::getResourceSingleton('catalog/product')
                ->refreshEnabledIndex($store);
        }
        return $this;
    }
}
