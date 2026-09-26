<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * @method Mage_Catalog_Model_Resource_Product_Website _getResource()
 * @method Mage_Catalog_Model_Resource_Product_Website getResource()
 */
class Mage_Catalog_Model_Product_Website extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_website');
    }

    /**
     * Removes products from websites
     *
     * @param array $websiteIds
     * @param array $productIds
     * @return $this
     */
    public function removeProducts($websiteIds, $productIds)
    {
        try {
            $this->_getResource()->removeProducts($websiteIds, $productIds);
        } catch (Exception) {
            Mage::throwException(
                Mage::helper('catalog')->__('An error occurred while removing products from websites.'),
            );
        }
        return $this;
    }

    /**
     * Add products to websites
     *
     * @param array $websiteIds
     * @param array $productIds
     * @return $this
     */
    public function addProducts($websiteIds, $productIds)
    {
        try {
            $this->_getResource()->addProducts($websiteIds, $productIds);
        } catch (Exception) {
            Mage::throwException(
                Mage::helper('catalog')->__('An error occurred while adding products to websites.'),
            );
        }
        return $this;
    }

    /**
     * Retrieve product websites
     * Return array with key as product ID and value array of websites
     *
     * @param int|array $productIds
     * @return array
     */
    public function getWebsites($productIds)
    {
        return $this->_getResource()->getWebsites($productIds);
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

}
