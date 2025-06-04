<?php

/**
 * Maho
 *
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Block_Category_Title extends Mage_Core_Block_Page_Title
{
    protected function _prepareLayout(): self
    {
        parent::_prepareLayout();

        if ($category = $this->getCurrentCategory()) {
            $helper = Mage::helper('catalog/output');
            $this->setTitle($helper->categoryAttribute($category, $category->getName(), 'name'));
        }

        return $this;
    }

    public function getShowRssLink(): bool
    {
        return $this->isRssCatalogEnable() && $this->isTopCategory();
    }

    public function getRssText(): string
    {
        return $this->__('Subscribe to RSS Feed');
    }

    public function getCurrentCategory(): ?Mage_Catalog_Model_Category
    {
        return Mage::registry('current_category');
    }

    public function isRssCatalogEnable(): bool
    {
        return (bool) Mage::getStoreConfig('rss/catalog/category');
    }

    public function isTopCategory(): bool
    {
        if ($category = $this->getCurrentCategory()) {
            return $category->getLevel() == 2;
        }
        return false;
    }

    public function buildRssLink(): string
    {
        return $this->getUrl('rss/catalog/category', [
            'cid' => $this->getCurrentCategory()->getId(),
            'store_id' => Mage::app()->getStore()->getId(),
        ]);
    }
}
