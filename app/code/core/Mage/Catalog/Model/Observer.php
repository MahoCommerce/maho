<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
            /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
            $categoryFlatHelper = Mage::helper('catalog/category_flat');
            if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
                Mage::getResourceModel('catalog/category_flat')->synchronize(null, [$store->getId()]);
            }
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
        /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
        $categoryFlatHelper = Mage::helper('catalog/category_flat');
        if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
            Mage::getResourceModel('catalog/category_flat')->synchronize(null, [$store->getId()]);
        }
        Mage::getResourceModel('catalog/product')->refreshEnabledIndex($store);
        return $this;
    }

    /**
     * Process catalog data related with store group root category
     *
     * @return  Mage_Catalog_Model_Observer
     */
    public function storeGroupSave(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Core_Model_Store_Group $group */
        $group = $observer->getEvent()->getGroup();
        if ($group->dataHasChangedFor('root_category_id') || $group->dataHasChangedFor('website_id')) {
            Mage::app()->reinitStores();
            foreach ($group->getStores() as $store) {
                /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
                $categoryFlatHelper = Mage::helper('catalog/category_flat');
                if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
                    Mage::getResourceModel('catalog/category_flat')->synchronize(null, [$store->getId()]);
                }
            }
        }
        return $this;
    }

    /**
     * Process delete of store
     *
     * @return $this
     */
    public function storeDelete(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
        $categoryFlatHelper = Mage::helper('catalog/category_flat');
        if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
            $store = $observer->getEvent()->getStore();
            Mage::getResourceModel('catalog/category_flat')->deleteStores($store->getId());
        }
        return $this;
    }

    /**
     * Process catalog data after category move
     *
     * @return  Mage_Catalog_Model_Observer
     */
    public function categoryMove(\Maho\Event\Observer $observer)
    {
        $categoryId = $observer->getEvent()->getCategoryId();
        $prevParentId = $observer->getEvent()->getPrevParentId();
        $parentId = $observer->getEvent()->getParentId();
        /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
        $categoryFlatHelper = Mage::helper('catalog/category_flat');
        if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
            Mage::getResourceModel('catalog/category_flat')->move($categoryId, $prevParentId, $parentId);
        }
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
     * Catalog Product Compare Items Clean
     *
     * @return $this
     */
    public function catalogProductCompareClean(\Maho\Event\Observer $observer)
    {
        Mage::getModel('catalog/product_compare_item')->clean();
        return $this;
    }

    /**
     * After save event of category
     *
     * @return $this
     */
    public function categorySaveAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Helper_Category_Flat $categoryFlatHelper */
        $categoryFlatHelper = Mage::helper('catalog/category_flat');
        if ($categoryFlatHelper->isAvailable() && $categoryFlatHelper->isBuilt()) {
            $category = $observer->getEvent()->getCategory();
            Mage::getResourceModel('catalog/category_flat')->synchronize($category);
        }

        return $this;
    }

    /**
     * Checking whether the using static urls in WYSIWYG allowed event
     */
    public function catalogCheckIsUsingStaticUrlsAllowed(\Maho\Event\Observer $observer)
    {
        $storeId = $observer->getEvent()->getData('store_id');
        $result  = $observer->getEvent()->getData('result');
        $result->isAllowed = Mage::helper('catalog')->setStoreId($storeId)->isUsingStaticUrlsAllowed();
    }

    /**
     * Cron job method for product prices to reindex
     */
    public function reindexProductPrices(Mage_Cron_Model_Schedule $schedule)
    {
        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
        if ($indexProcess) {
            $indexProcess->reindexAll();
        }
    }


    /**
     * Adds catalog categories to top menu
     */
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

            $flatHelper = Mage::helper('catalog/category_flat');
            if ($flatHelper->isEnabled() && $flatHelper->isBuilt(true)) {
                $subcategories = (array) $category->getChildrenNodes();
            } else {
                $subcategories = $category->getChildren();
            }

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
    public function addFileElementType(\Maho\Event\Observer $observer)
    {
        $response = $observer->getEvent()->getResponse();
        $types = $response->getTypes();
        $types['file'] = Mage::getConfig()->getBlockClassName('adminhtml/catalog_product_helper_form_file');
        $response->setTypes($types);

        return $this;
    }
}
