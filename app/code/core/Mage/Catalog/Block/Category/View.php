<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Block_Category_View extends Mage_Core_Block_Template
{
    /**
     * Memoized getForcedRobots() result for the render; false until resolved.
     */
    protected string|false|null $_forcedRobots = false;

    /**
     * Cached list of request vars a layered-navigation filter can occupy. The filterable
     * set is the same for every request, so it is cached per process.
     *
     * @var string[]|null
     */
    protected static ?array $_filterableRequestVars = null;

    /**
     * @return $this|Mage_Core_Block_Template
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->getLayout()->createBlock('catalog/breadcrumbs');

        $category = $this->getCurrentCategory();

        /** @var Mage_Page_Block_Html_Head $headBlock */
        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            if ($title = $category->getMetaTitle()) {
                $headBlock->setTitle($title);
            }
            if ($description = $category->getMetaDescription()) {
                $headBlock->setDescription($description);
            }
            if ($keywords = $category->getMetaKeywords()) {
                $headBlock->setKeywords($keywords);
            }

            // A view is either indexable (canonical to the clean URL) or suppressed
            // (NOINDEX,FOLLOW, no canonical), never both: a noindex plus a cross-URL
            // canonical is contradictory and can deindex the canonical target.
            if ($robots = $this->getForcedRobots()) {
                $headBlock->setRobots($robots);
            } elseif ($this->shouldUseCanonicalTag()) {
                $headBlock->addLinkRel('canonical', $category->getUrl());
            }

            // Add rss feed in head block
            if ($this->isRssCatalogEnable() && $this->isTopCategory()) {
                $title = $this->helper('rss')->__('%s RSS Feed', $this->getCurrentCategory()->getName());
                $headBlock->addItem('rss', $this->getRssLink(), 'title="' . $title . '"');
            }
        }

        /** @var Mage_Page_Block_Html_Head */
        $titleBlock = $this->getLayout()->getBlock('title');
        if ($titleBlock) {
            $titleBlock->setTitle($this->helper('catalog/output')->categoryAttribute($category, $category->getName(), 'name'));

            // Add rss feed in title block
            if ($this->isRssCatalogEnable() && $this->isTopCategory()) {
                $titleBlock->getLinksBlock()->addLink(
                    $this->getIconSvg('rss'),
                    $this->getRssLink(),
                    $this->helper('rss')->__('Subscribe to RSS Feed'),
                );
            }
        }

        return $this;
    }

    /**
     * The noindex directive for this view, or null when indexable. Single source of
     * truth for "is this view noindexed?", memoized for the render.
     *
     * Precedence: an explicit category noindex always wins (and suppresses the
     * canonical). Otherwise filtered and paginated layered-navigation views are forced
     * to NOINDEX,FOLLOW as duplicates, even when the category is explicitly indexable;
     * facet landing pages opt out. A base view with no noindex yields null, leaving the
     * head block to render the category's own robots.
     */
    public function getForcedRobots(): ?string
    {
        if ($this->_forcedRobots !== false) {
            return $this->_forcedRobots;
        }

        // An explicit category noindex wins everywhere, so the admin directive is never
        // weakened and the canonical is suppressed.
        $categoryRobots = (string) $this->getCurrentCategory()->getMetaRobots();
        if ($categoryRobots !== '' && stripos($categoryRobots, 'noindex') !== false) {
            return $this->_forcedRobots = $categoryRobots;
        }

        // Filtered and paginated views are duplicates regardless of the base category's
        // robots, so they are noindexed even when the category is explicitly indexable.
        /** @var Mage_Catalog_Helper_Category $helper */
        $helper = $this->helper('catalog/category');
        if (!$helper->isLayeredNavigationLandingPage()
            && (($helper->canUseNoindexForFilteredPages() && $this->hasActiveFilters())
                || ($helper->canUseNoindexForPaginatedPages() && $this->isPaginated()))
        ) {
            return $this->_forcedRobots = 'NOINDEX,FOLLOW';
        }

        return $this->_forcedRobots = null;
    }

    /**
     * Whether to advertise a canonical URL for this view. Suppressed on a noindexed view
     * (canonical and noindex are mutually exclusive) and on facet landing pages, which
     * manage their own canonical.
     */
    public function shouldUseCanonicalTag(): bool
    {
        if ($this->getForcedRobots()) {
            return false;
        }

        /** @var Mage_Catalog_Helper_Category $helper */
        $helper = $this->helper('catalog/category');
        return $helper->canUseCanonicalTag()
            && !$helper->isLayeredNavigationLandingPage();
    }

    /**
     * Whether any layered-navigation filter is active, detected straight from the
     * request so the result does not depend on the leftnav block having applied the
     * layer first (render-order independent).
     */
    public function hasActiveFilters(): bool
    {
        $request = $this->getRequest();
        foreach ($this->getFilterableRequestVars() as $requestVar) {
            $value = $request->getParam($requestVar);
            if ($value !== null && $value !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Request vars a layered-navigation filter can occupy: the category (cat) filter
     * plus every globally filterable product attribute (price and select/decimal
     * attributes). Cached per process.
     *
     * @return string[]
     */
    public function getFilterableRequestVars(): array
    {
        if (self::$_filterableRequestVars === null) {
            $vars = ['cat'];
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                ->addIsFilterableFilter();
            foreach ($attributes as $attribute) {
                $vars[] = $attribute->getAttributeCode();
            }
            self::$_filterableRequestVars = $vars;
        }
        return self::$_filterableRequestVars;
    }

    /**
     * Whether the current request is a paginated category page (page > 1), using the
     * catalog product-list toolbar's configured page var (falling back to the default)
     * so detection tracks a rewritten toolbar's page var.
     */
    public function isPaginated(): bool
    {
        /** @var Mage_Catalog_Block_Product_List_Toolbar $toolbar */
        $toolbar = Mage::getBlockSingleton('catalog/product_list_toolbar');
        $pageVar = $toolbar ? $toolbar->getPageVarName() : 'p';
        return (int) $this->getRequest()->getParam($pageVar, 1) > 1;
    }

    /**
     * @return string
     */
    public function isRssCatalogEnable()
    {
        return Mage::getStoreConfig('rss/catalog/category');
    }

    /**
     * @return bool
     */
    public function isTopCategory()
    {
        return $this->getCurrentCategory()->getLevel() == 2;
    }

    /**
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getRssLink()
    {
        return Mage::getUrl(
            'rss/catalog/category',
            [
                'cid' => $this->getCurrentCategory()->getId(),
                'store_id' => Mage::app()->getStore()->getId(),
            ],
        );
    }

    /**
     * @return string
     */
    public function getProductListHtml()
    {
        return $this->getChildHtml('product_list');
    }

    /**
     * Retrieve current category model object
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCurrentCategory()
    {
        if (!$this->hasData('current_category')) {
            $this->setData('current_category', Mage::registry('current_category'));
        }
        return $this->getData('current_category');
    }

    /**
     * @return string
     */
    public function getCmsBlockHtml()
    {
        if (!$this->getData('cms_block_html')) {
            $html = $this->getLayout()->createBlock('cms/block')
                ->setBlockId($this->getCurrentCategory()->getLandingPage())
                ->toHtml();
            $this->setData('cms_block_html', $html);
        }
        return $this->getData('cms_block_html');
    }

    /**
     * Check if category display mode is "Products Only"
     * @return bool
     */
    public function isProductMode()
    {
        return $this->getCurrentCategory()->getDisplayMode() == Mage_Catalog_Model_Category::DM_PRODUCT;
    }

    /**
     * Check if category display mode is "Static Block and Products"
     * @return bool
     */
    public function isMixedMode()
    {
        return $this->getCurrentCategory()->getDisplayMode() == Mage_Catalog_Model_Category::DM_MIXED;
    }

    /**
     * Check if category display mode is "Static Block Only"
     * For anchor category with applied filter Static Block Only mode not allowed
     *
     * @return bool
     */
    public function isContentMode()
    {
        $category = $this->getCurrentCategory();
        $res = false;
        if ($category->getDisplayMode() == Mage_Catalog_Model_Category::DM_PAGE) {
            $res = true;
            if ($category->getIsAnchor()) {
                $state = Mage::getSingleton('catalog/layer')->getState();
                if ($state && $state->getFilters()) {
                    $res = false;
                }
            }
        }
        return $res;
    }

    /**
     * Retrieve block cache tags based on category
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), $this->getCurrentCategory()->getCacheIdTags());
    }
}
