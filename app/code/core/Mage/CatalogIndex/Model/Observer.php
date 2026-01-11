<?php

/**
 * Maho
 *
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogIndex_Model_Observer extends Mage_Core_Model_Abstract
{
    protected $_parentProductIds = [];
    protected $_productIdsMassupdate = [];

    #[\Override]
    protected function _construct() {}

    /**
     * Get indexer object
     *
     * @return Mage_CatalogIndex_Model_Indexer
     */
    protected function _getIndexer()
    {
        return Mage::getSingleton('catalogindex/indexer');
    }

    /**
     * Get aggregation object
     *
     * @return Mage_CatalogIndex_Model_Aggregation
     */
    protected function _getAggregator()
    {
        return Mage::getSingleton('catalogindex/aggregation');
    }

    /**
     * Reindex all catalog data
     *
     * @return $this
     */
    public function reindexAll()
    {
        $this->_getIndexer()->plainReindex();
        $this->_getAggregator()->clearCacheData();
        return $this;
    }

    /**
     * Reindex daily related data (prices)
     *
     * @return $this
     */
    public function reindexDaily()
    {
        $this->_getIndexer()->plainReindex(
            null,
            Mage_CatalogIndex_Model_Indexer::REINDEX_TYPE_PRICE,
        );
        $this->clearPriceAggregation();
        return $this;
    }

    /**
     * Process product after save
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processAfterSaveEvent(\Maho\Event\Observer $observer)
    {
        $productIds = [];
        /** @var Mage_Catalog_Model_Product $eventProduct */
        $eventProduct = $observer->getEvent()->getProduct();
        $productIds[] = $eventProduct->getId();

        if (!$eventProduct->getIsMassupdate()) {
            $this->_getIndexer()->plainReindex($eventProduct);
        } else {
            $this->_productIdsMassupdate[] = $eventProduct->getId();
        }
        $this->_getAggregator()->clearProductData($productIds);
        return $this;
    }

    /**
     * Reindex price data after attribute scope change
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processPriceScopeChange(\Maho\Event\Observer $observer)
    {
        $configOption   = $observer->getEvent()->getOption();
        if ($configOption->isValueChanged()) {
            $this->_getIndexer()->plainReindex(
                null,
                Mage_CatalogIndex_Model_Indexer::REINDEX_TYPE_PRICE,
            );
            $this->clearPriceAggregation();
        }
        return $this;
    }

    /**
     * Process catalog index after price rules were applied
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processPriceRuleApplication(\Maho\Event\Observer $observer)
    {
        $eventProduct = $observer->getEvent()->getProduct();
        $productCondition = $observer->getEvent()->getProductCondition();
        if ($productCondition) {
            $eventProduct = $productCondition;
        }
        $this->_getIndexer()->plainReindex(
            $eventProduct,
            Mage_CatalogIndex_Model_Indexer::REINDEX_TYPE_PRICE,
        );

        $this->clearPriceAggregation();
        return $this;
    }

    /**
     * Cleanup product index after product delete
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processAfterDeleteEvent(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $eventProduct */
        $eventProduct = $observer->getEvent()->getProduct();
        $eventProduct->setNeedStoreForReindex(true);
        $this->_getIndexer()->cleanup($eventProduct);
        $parentProductIds = $eventProduct->getParentProductIds();

        if ($parentProductIds) {
            $this->_getIndexer()->plainReindex($parentProductIds);
        }
        return $this;
    }

    /**
     * Process index data after attribute information was changed
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processAttributeChangeEvent(\Maho\Event\Observer $observer)
    {
        /**
         * @todo add flag to attribute model which will notify what options was changed
         */
        $attribute = $observer->getEvent()->getAttribute();
        $tags = [
            Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId(),
        ];

        if ($attribute->getOrigData('is_filterable') != $attribute->getIsFilterable()) {
            if ($attribute->getIsFilterable() != 0) {
                $this->_getIndexer()->plainReindex(null, $attribute);
            } else {
                $this->_getAggregator()->clearCacheData($tags);
            }
        } elseif ($attribute->getIsFilterable()) {
            $this->_getAggregator()->clearCacheData($tags);
        }

        return $this;
    }

    /**
     * Create index for new store
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processStoreAdd(\Maho\Event\Observer $observer)
    {
        $store = $observer->getEvent()->getStore();
        $this->_getIndexer()->plainReindex(null, null, $store);
        return $this;
    }

    /**
     * Rebuild index after catalog import
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function catalogProductImportAfter(\Maho\Event\Observer $observer)
    {
        $this->_getIndexer()->plainReindex();
        $this->_getAggregator()->clearCacheData();
        return $this;
    }

    /**
     * Run planned reindex
     *
     * @return $this
     */
    public function runQueuedIndexing()
    {
        $flag = Mage::getModel('catalogindex/catalog_index_flag')->loadSelf();
        if ($flag->getState() == Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_QUEUED) {
            $this->_getIndexer()->plainReindex();
            $this->_getAggregator()->clearCacheData();
        }
        return $this;
    }

    /**
     * Clear aggregated layered navigation data
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function cleanCache(\Maho\Event\Observer $observer)
    {
        $tagsArray = $observer->getEvent()->getTags();
        $tagName = Mage_CatalogIndex_Model_Aggregation::CACHE_FLAG_NAME;

        if (empty($tagsArray) || in_array($tagName, $tagsArray)) {
            $this->_getAggregator()->clearCacheData();
        }
        return $this;
    }

    /**
     * Process index data after category save
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function catalogCategorySaveAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = $observer->getEvent()->getCategory();
        if ($category->getInitialSetupFlag()) {
            return $this;
        }
        $tags = [
            Mage_Catalog_Model_Category::CACHE_TAG . ':' . $category->getPath(),
        ];
        $this->_getAggregator()->clearCacheData($tags);
        return $this;
    }

    /**
     * Delete price aggreagation data
     *
     * @return $this
     */
    public function clearPriceAggregation()
    {
        $this->_getAggregator()->clearCacheData([
            Mage_Catalog_Model_Product_Type_Price::CACHE_TAG,
        ]);
        return $this;
    }

    /**
     * Clear layer navigation cache for search results
     *
     * @return $this
     */
    public function clearSearchLayerCache()
    {
        $this->_getAggregator()->clearCacheData([
            Mage_CatalogSearch_Model_Query::CACHE_TAG,
        ]);
        return $this;
    }

    /**
     * Load parent ids for products before deleting
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function registerParentIds(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getEvent()->getProduct();
        $this->_getAggregator()->clearProductData([$product->getId()]);
        return $this;
    }

    /**
     * Reindex producs after change websites associations
     *
     * @return  Mage_CatalogIndex_Model_Observer
     */
    public function processProductsWebsitesChange(\Maho\Event\Observer $observer)
    {
        $productIds = $observer->getEvent()->getProducts();
        $this->_getIndexer()->plainReindex($productIds);
        $this->_getAggregator()->clearProductData($productIds);
        return $this;
    }

    /**
     * Prepare columns for catalog product flat
     *
     * @return $this
     */
    public function catalogProductFlatPrepareColumns(\Maho\Event\Observer $observer)
    {
        $columns = $observer->getEvent()->getColumns();

        $this->_getIndexer()->prepareCatalogProductFlatColumns($columns);

        return $this;
    }

    /**
     * Prepare indexes for catalog product flat
     *
     * @return $this
     */
    public function catalogProductFlatPrepareIndexes(\Maho\Event\Observer $observer)
    {
        $indexes = $observer->getEvent()->getIndexes();

        $this->_getIndexer()->prepareCatalogProductFlatIndexes($indexes);

        return $this;
    }

    /**
     * Rebuild catalog product flat
     *
     * @return $this
     */
    public function catalogProductFlatRebuild(\Maho\Event\Observer $observer)
    {
        $storeId    = $observer->getEvent()->getStoreId();
        $tableName  = $observer->getEvent()->getTable();

        $this->_getIndexer()->updateCatalogProductFlat($storeId, null, $tableName);

        return $this;
    }

    /**
     * Catalog Product Flat update product(s)
     *
     * @return $this
     */
    public function catalogProductFlatUpdateProduct(\Maho\Event\Observer $observer)
    {
        $storeId    = $observer->getEvent()->getStoreId();
        $tableName  = $observer->getEvent()->getTable();
        $productIds = $observer->getEvent()->getProductIds();

        $this->_getIndexer()->updateCatalogProductFlat($storeId, $productIds, $tableName);

        return $this;
    }
}
