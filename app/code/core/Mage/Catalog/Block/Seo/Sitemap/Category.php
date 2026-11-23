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
 * SEO Categories Sitemap block
 *
 * @package    Mage_Catalog
 * @extends Mage_Catalog_Block_Seo_Sitemap_Abstract<Mage_Catalog_Model_Category>
 */
class Mage_Catalog_Block_Seo_Sitemap_Category extends Mage_Catalog_Block_Seo_Sitemap_Abstract
{
    /**
     * Initialize categories collection
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $helper = Mage::helper('catalog/category');
        /** @var Mage_Catalog_Helper_Category $helper */
        $collection = $helper->getStoreCategories('name', true, false);
        $this->setCollection($collection);
        return $this;
    }

    /**
     * @param Mage_Catalog_Model_Category $category
     */
    #[\Override]
    public function getItemUrl(Mage_Catalog_Model_Abstract $category): string
    {
        $helper = Mage::helper('catalog/category');
        /** @var Mage_Catalog_Helper_Category $helper */
        return $helper->getCategoryUrl($category);
    }

    public function setCollection(array|Mage_Catalog_Model_Resource_Category_Collection|\Maho\Data\Collection|\Maho\Data\Tree\Node\Collection|null $value): static
    {
        return $this->setData('collection', $value);
    }
}
