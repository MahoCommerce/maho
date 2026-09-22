<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

/**
 * @method Mage_Reports_Model_Resource_Product_Index_Viewed _getResource()
 * @method Mage_Reports_Model_Resource_Product_Index_Viewed getResource()
 */
class Mage_Reports_Model_Product_Index_Viewed extends Mage_Reports_Model_Product_Index_Abstract
{
    /**
     * Cache key name for Count of product index
     *
     * @var string
     */
    #[\Override]
    protected $_countCacheKey   = 'product_index_viewed_count';

    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/product_index_viewed');
    }

    /**
     * Retrieve Exclude Product Ids List for Collection
     *
     * @return array
     */
    #[\Override]
    public function getExcludeProductIds()
    {
        $productIds = [];

        if (Mage::registry('current_product')) {
            $productIds[] = Mage::registry('current_product')->getId();
        }

        return $productIds;
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

}
