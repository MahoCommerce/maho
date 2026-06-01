<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Block_Category_View extends Mage_Core_Block_Template
{
    /**
     * Memoized getForcedRobots() result for the render; false until resolved.
     *
     * @var string|null|false
     */
    protected $_forcedRobots = false;

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
            // (NOINDEX,FOLLOW, no canonical) — never both, since a noindex plus a
            // cross-URL canonical is contradictory and can deindex the canonical target.
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
     * The noindex directive applying to this view, or null when it is indexable.
     * Filtered and paginated layered-navigation pages are forced to NOINDEX,FOLLOW. An
     * explicit category Meta Robots value takes precedence: a noindex one is surfaced
     * here (so the canonical is suppressed), while an index one yields null and is left
     * for the head block to render. Facet landing pages are never force-noindexed.
     * This is the single source of truth for "is this view noindexed?". Memoized.
     *
     * @return string|null
     */
    public function getForcedRobots()
    {
        if ($this->_forcedRobots !== false) {
            return $this->_forcedRobots;
        }

        // An explicit category Meta Robots value wins; surface it only when it is a
        // noindex, so the canonical is suppressed. An INDEX value is rendered by the head
        // block from the category itself and leaves the canonical free.
        $categoryRobots = (string) $this->getCurrentCategory()->getMetaRobots();
        if ($categoryRobots !== '') {
            return $this->_forcedRobots = stripos($categoryRobots, 'noindex') !== false ? $categoryRobots : null;
        }

        /** @var Mage_Catalog_Helper_Category $helper */
        $helper = $this->helper('catalog/category');
        if (!$helper->isLayeredNavigationLandingPage()
            && (($this->hasActiveFilters() && $helper->canUseNoindexForFilteredPages())
                || ($this->isPaginated() && $helper->canUseNoindexForPaginatedPages()))
        ) {
            return $this->_forcedRobots = 'NOINDEX,FOLLOW';
        }

        return $this->_forcedRobots = null;
    }

    /**
     * Whether to advertise a canonical URL for this view. Suppressed on a noindexed view
     * (canonical and noindex are mutually exclusive) and on facet landing pages, which
     * manage their own canonical.
     *
     * @return bool
     */
    public function shouldUseCanonicalTag()
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
     * Whether a layered-navigation filter other than the subcategory (cat) filter is
     * active. Subcategory drill-down is legitimate, indexable navigation, not a facet.
     *
     * @return bool
     */
    public function hasActiveFilters()
    {
        $state = Mage::getSingleton('catalog/layer')->getState();
        foreach ($state->getFilters() as $item) {
            $filter = $item->getData('filter');
            if ($filter && $filter->getRequestVar() !== 'cat') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the current request is a paginated category page (page > 1). Detection uses
     * the default 'p' page var, the same one Maho's layered navigation assumes.
     *
     * @return bool
     */
    public function isPaginated()
    {
        return (int) $this->getRequest()->getParam('p', 1) > 1;
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
