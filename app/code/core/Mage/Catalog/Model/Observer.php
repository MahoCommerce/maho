<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Observer
{
    /**
     * Process catalog ata related with store data changes
     *
     * @return  Mage_Catalog_Model_Observer
     */
    public function storeEdit(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Core_Model_Store $store */
        $store = $observer->getEvent()->getStore();
        if ($store->dataHasChangedFor('group_id')) {
            Mage::app()->reinitStores();
            Mage::getResourceSingleton('catalog/product')->refreshEnabledIndex($store);
        }
        return $this;
    }

    /**
     * Process catalog data related with new store
     *
     * @return  Mage_Catalog_Model_Observer
     */
    public function storeAdd(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Core_Model_Store $store */
        $store = $observer->getEvent()->getStore();
        Mage::app()->reinitStores();
        Mage::getConfig()->reinit();
        Mage::getResourceModel('catalog/product')->refreshEnabledIndex($store);
        return $this;
    }

    /**
     * Process catalog data after products import
     *
     * @return  Mage_Catalog_Model_Observer
     */
    public function catalogProductImportAfter(\Maho\Event\Observer $observer)
    {
        Mage::getModel('catalog/url')->refreshRewrites();
        Mage::getResourceSingleton('catalog/category')->refreshProductIndex();
        return $this;
    }

    /**
     * Queue the warm-up of the role images of a saved product when one of them changed.
     * The message joins the transaction of the save.
     */
    #[Maho\Config\Observer('catalog_product_save_after')]
    public function queueProductImageWarmUp(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getEvent()->getProduct();
        foreach (Mage_Catalog_Model_Product_Image_Warmer::ROLES as $role) {
            if ($product->dataHasChangedFor($role)) {
                Mage::getSingleton('catalog/product_image_warmer')->queue([(int) $product->getId()]);
                return;
            }
        }
    }

    #[Maho\Config\Observer('catalog_product_import_finish_before')]
    public function queueImportedProductImageWarmUp(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_ImportExport_Model_Import_Entity_Product $adapter */
        $adapter = $observer->getEvent()->getAdapter();
        Mage::getSingleton('catalog/product_image_warmer')->queue($adapter->getAffectedEntityIds());
    }

    /**
     * Catalog Product Compare Items Clean
     *
     * @return $this
     */
    #[Maho\Config\Observer('log_log_clean_after')]
    public function catalogProductCompareClean(\Maho\Event\Observer $observer)
    {
        Mage::getModel('catalog/product_compare_item')->clean();
        return $this;
    }

    /**
     * Checking whether the using static urls in WYSIWYG allowed event
     */
    #[Maho\Config\Observer('cms_wysiwyg_images_static_urls_allowed', area: 'adminhtml')]
    public function catalogCheckIsUsingStaticUrlsAllowed(\Maho\Event\Observer $observer)
    {
        $storeId = $observer->getEvent()->getData('store_id');
        $result  = $observer->getEvent()->getData('result');
        $result->isAllowed = Mage::helper('catalog')->setStoreId($storeId)->isUsingStaticUrlsAllowed();
    }

    /**
     * Cron job method for product prices to reindex
     */
    #[Maho\Config\CronJob('catalog_product_index_price_reindex_all', schedule: '0 2 * * *')]
    public function reindexProductPrices(Mage_Cron_Model_Schedule $schedule)
    {
        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
        if ($indexProcess) {
            $indexProcess->reindexAll();
        }
    }

    /**
     * Remember the URLs of a product before the delete cascade removes its rewrites,
     * so the no-route page can answer 410 Gone instead of 404 Not Found
     */
    #[Maho\Config\Observer('catalog_product_delete_before')]
    public function markProductUrlsGone(\Maho\Event\Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        Mage::getResourceSingleton('catalog/url')->markProductRewritesGone((int) $product->getId());
    }

    #[Maho\Config\Observer('directory_currency_rates_save_after')]
    public function invalidateProductPriceIndex(\Maho\Event\Observer $observer): void
    {
        if (!Mage::helper('catalog')->ratesChangeWebsitePrices((array) $observer->getEvent()->getData('rates'))) {
            return;
        }

        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
        if ($indexProcess) {
            $indexProcess->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
        }
    }


    /**
     * Adds catalog categories to top menu
     */
    #[Maho\Config\Observer('page_block_html_topmenu_gethtml_before')]
    public function addCatalogToTopmenuItems(\Maho\Event\Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        $block->addCacheTag(Mage_Catalog_Model_Category::CACHE_TAG);
        $this->_addCategoriesToMenu(
            Mage::helper('catalog/category')->getStoreCategories(),
            $observer->getMenu(),
            $block,
        );
    }

    /**
     * Recursively adds categories to top menu
     *
     * @param \Maho\Data\Tree\Node\Collection|array $categories
     * @param \Maho\Data\Tree\Node $parentCategoryNode
     * @param Mage_Page_Block_Html_Topmenu $menuBlock
     * @param bool $addTags
     */
    protected function _addCategoriesToMenu($categories, $parentCategoryNode, $menuBlock, $addTags = false)
    {
        $categoryModel = Mage::getModel('catalog/category');
        foreach ($categories as $category) {
            if (!$category->getIsActive()) {
                continue;
            }

            $nodeId = 'category-node-' . $category->getId();

            $categoryModel->setId($category->getId());
            if ($addTags) {
                $menuBlock->addModelTags($categoryModel);
            }

            $tree = $parentCategoryNode->getTree();
            $categoryData = [
                'name' => $category->getName(),
                'id' => $nodeId,
                'url' => Mage::helper('catalog/category')->getCategoryUrl($category),
                'is_active' => $this->_isActiveMenuCategory($category),
            ];
            $categoryNode = new \Maho\Data\Tree\Node($categoryData, 'id', $tree, $parentCategoryNode);
            $parentCategoryNode->addChild($categoryNode);

            $subcategories = $category->getChildren();

            $this->_addCategoriesToMenu($subcategories, $categoryNode, $menuBlock, $addTags);
        }
    }

    /**
     * Checks whether category belongs to active category's path
     *
     * @param \Maho\Data\Tree\Node $category
     * @return bool
     */
    protected function _isActiveMenuCategory($category)
    {
        $catalogLayer = Mage::getSingleton('catalog/layer');
        if (!$catalogLayer) {
            return false;
        }

        $currentCategory = $catalogLayer->getCurrentCategory();
        if (!$currentCategory) {
            return false;
        }

        $categoryPathIds = explode(',', $currentCategory->getPathInStore());
        return in_array($category->getId(), $categoryPathIds);
    }

    /**
     * Checks whether attribute_code by current module is reserved
     *
     * @throws Mage_Core_Exception
     */
    #[Maho\Config\Observer('catalog_entity_attribute_save_before')]
    public function checkReservedAttributeCodes(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Entity_Attribute $attribute */
        $attribute = $observer->getEvent()->getAttribute();
        if (!is_object($attribute)) {
            return;
        }
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        if ($product->isReservedAttribute($attribute)) {
            throw new Mage_Core_Exception(
                Mage::helper('catalog')->__('The attribute code \'%s\' is reserved by system. Please try another attribute code', $attribute->getAttributeCode()),
            );
        }
    }

    /**
     * Add file attribute type to product attributes
     *
     * @return $this
     */
    #[Maho\Config\Observer('adminhtml_product_attribute_types', area: 'adminhtml')]
    public function addFileAttributeType(\Maho\Event\Observer $observer)
    {
        $response = $observer->getEvent()->getResponse();
        $types = $response->getTypes();
        $types[] = [
            'value' => 'file',
            'label' => Mage::helper('catalog')->__('File'),
            'hide_fields' => [
                'is_searchable',
                'is_visible_in_advanced_search',
                'is_filterable',
                'is_filterable_multiple',
                'is_filterable_in_search',
                'is_comparable',
                'is_used_for_promo_rules',
                'used_for_sort_by',
                'is_wysiwyg_enabled',
                'is_html_allowed_on_front',
            ],
        ];

        $response->setTypes($types);

        return $this;
    }

    /**
     * Add file element type for product edit form
     *
     * @return $this
     */
    #[Maho\Config\Observer('adminhtml_catalog_product_edit_element_types', area: 'adminhtml')]
    public function addFileElementType(\Maho\Event\Observer $observer)
    {
        $response = $observer->getEvent()->getResponse();
        $types = $response->getTypes();
        $types['file'] = Mage::getConfig()->getBlockClassName('adminhtml/catalog_product_helper_form_file');
        $response->setTypes($types);

        return $this;
    }
}
