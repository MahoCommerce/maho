<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Block_Product_Compared extends Mage_Reports_Block_Product_Abstract
{
    public const XML_PATH_RECENTLY_COMPARED_COUNT  = 'catalog/recently_products/compared_count';

    /**
     * Compared Product Index model name
     *
     * @var string
     */
    #[\Override]
    protected $_indexName = 'reports/product_index_compared';

    /**
     * Retrieve page size (count)
     *
     * @return int
     */
    #[\Override]
    public function getPageSize()
    {
        if ($this->hasData('page_size')) {
            return $this->getData('page_size');
        }
        return Mage::getStoreConfig(self::XML_PATH_RECENTLY_COMPARED_COUNT);
    }

    /**
     * Prepare to html
     * Check has compared products
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!Mage::helper('reports')->isProductCompareEnabled()) {
            return '';
        }
        if (!$this->getCount()) {
            return '';
        }

        $this->setRecentlyComparedProducts($this->getItemsCollection());

        return parent::_toHtml();
    }

    /**
     * Retrieve block cache tags
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        return array_merge(
            parent::getCacheTags(),
            $this->getItemsTags($this->getItemsCollection()),
        );
    }

    public function setRecentlyComparedProducts(?Mage_Reports_Model_Resource_Product_Index_Collection_Abstract $value): static
    {
        return $this->setData('recently_compared_products', $value);
    }

    public function getRecentlyComparedProducts(): ?Mage_Reports_Model_Resource_Product_Index_Collection_Abstract
    {
        return $this->getData('recently_compared_products');
    }
}
