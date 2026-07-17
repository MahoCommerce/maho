<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * SEO Products Sitemap block
 *
 * @package    Mage_Catalog
 *
 * @method $this setCollection(Mage_Catalog_Model_Resource_Product_Collection $value)
 */
class Mage_Catalog_Block_Seo_Sitemap_Product extends Mage_Catalog_Block_Seo_Sitemap_Abstract
{
    /**
     * Initialize products collection
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addStoreFilter()
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
            ->addAttributeToFilter('status', [
                'in' => Mage::getSingleton('catalog/product_status')->getVisibleStatusIds(),
            ]);

        $this->setCollection($collection);
        return $this;
    }

    /**
     * Get item URL
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    #[\Override]
    public function getItemUrl($product)
    {
        /** @var Mage_Catalog_Helper_Product $helper */
        $helper = Mage::helper('catalog/product');
        return $helper->getProductUrl($product);
    }
}
