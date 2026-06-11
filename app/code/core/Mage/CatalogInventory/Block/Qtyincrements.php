<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

class Mage_CatalogInventory_Block_Qtyincrements extends Mage_Core_Block_Template
{
    /**
     * Qty Increments cache
     *
     * @var float|false
     */
    protected $_qtyIncrements;

    /**
     * Retrieve current product object
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Retrieve current product name
     *
     * @return string
     */
    public function getProductName()
    {
        return $this->_getProduct()->getName();
    }

    /**
     * Retrieve product qty increments
     *
     * @return float|false
     */
    public function getProductQtyIncrements()
    {
        if ($this->_qtyIncrements === null) {
            $this->_qtyIncrements = $this->_getProduct()->getStockItem()->getQtyIncrements();
            if (!$this->_getProduct()->isSaleable()) {
                $this->_qtyIncrements = false;
            }
        }
        return $this->_qtyIncrements;
    }

    /**
     * Retrieve block cache tags
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), $this->_getProduct()->getCacheIdTags());
    }
}
