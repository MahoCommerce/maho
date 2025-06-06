<?php

/**
 * Maho
 *
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * CatalogIndex Index operation model
 *
 * @package    Mage_CatalogIndex
 *
 * @method Mage_CatalogIndex_Model_Resource_Indexer _getResource()
 * @method Mage_CatalogIndex_Model_Resource_Indexer getResource()
 * @method int getEntityTypeId()
 * @method $this setEntityTypeId(int $value)
 * @method int getAttributeSetId()
 * @method $this setAttributeSetId(int $value)
 * @method string getTypeId()
 * @method $this setTypeId(string $value)
 * @method string getSku()
 * @method $this setSku(string $value)
 * @method int getHasOptions()
 * @method $this setHasOptions(int $value)
 * @method int getRequiredOptions()
 * @method $this setRequiredOptions(int $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class Mage_CatalogIndex_Model_Indexer extends Mage_Core_Model_Abstract
{
    public const REINDEX_TYPE_ALL = 0;
    public const REINDEX_TYPE_PRICE = 1;
    public const REINDEX_TYPE_ATTRIBUTE = 2;

    public const STEP_SIZE = 1000;

    /**
     * Set of available indexers
     * Each indexer type is responsible for index data storage
     *
     * @var array
     */
    protected $_indexers = [];

    /**
     * Predefined set of indexer types which are related with product price
     *
     * @var array
     */
    protected $_priceIndexers = ['price', 'tier_price', 'minimal_price'];

    /**
     * Predefined sets of indexer types which are related
     * with product filterable attributes
     *
     * @var array
     */
    protected $_attributeIndexers = ['eav'];

    /**
     * Tproduct types sorted by index priority
     *
     * @var array|null
     */
    protected $_productTypePriority = null;

    /**
     * Initialize all indexers and resource model
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_loadIndexers();
        $this->_init('catalogindex/indexer');
    }

    /**
     * Create instances of all index types
     *
     * @return $this
     */
    protected function _loadIndexers()
    {
        foreach ($this->_getRegisteredIndexers() as $name => $class) {
            $this->_indexers[$name] = Mage::getSingleton($class);
        }
        return $this;
    }

    /**
     * Get all registered in configuration indexers
     *
     * @return array
     */
    protected function _getRegisteredIndexers()
    {
        $result = [];
        $indexerRegistry = Mage::getConfig()->getNode('global/catalogindex/indexer');

        foreach ($indexerRegistry->children() as $node) {
            $result[$node->getName()] = (string) $node->class;
        }
        return $result;
    }

    /**
     * Get array of attribute codes required for indexing
     * Each indexer type provide his own set of attributes
     *
     * @return array
     */
    protected function _getIndexableAttributeCodes()
    {
        $result = [];
        foreach ($this->_indexers as $indexer) {
            $codes = $indexer->getIndexableAttributeCodes();

            if (is_array($codes)) {
                $result = array_merge($result, $codes);
            }
        }
        return $result;
    }

    /**
     * Retrieve store collection
     *
     * @return array
     */
    protected function _getStores()
    {
        $stores = $this->getData('_stores');
        if (is_null($stores)) {
            $stores = Mage::app()->getStores();
            $this->setData('_stores', $stores);
        }
        return $stores;
    }

    /**
     * Retrieve store collection
     *
     * @return Mage_Core_Model_Resource_Website_Collection
     */
    protected function _getWebsites()
    {
        $websites = $this->getData('_websites');
        if (is_null($websites)) {
            /** @var Mage_Core_Model_Resource_Website_Collection $websites */
            $websites = Mage::getModel('core/website')->getCollection()->load();

            $this->setData('_websites', $websites);
        }
        return $websites;
    }

    /**
     * Remove index data for specifuc product
     *
     * @param   mixed $product
     * @return  Mage_CatalogIndex_Model_Indexer
     */
    public function cleanup($product)
    {
        $store = $product->getNeedStoreForReindex() === true ? $this->_getStores() : null;
        $this->_getResource()->clear(true, true, true, true, true, $product, $store);

        return $this;
    }

    /**
     * Reindex catalog product data which used in layered navigation and in product list
     *
     * @param   mixed $products
     * @param   mixed $attributes
     * @param   mixed $stores
     * @return  Mage_CatalogIndex_Model_Indexer
     */
    public function plainReindex($products = null, $attributes = null, $stores = null)
    {
        /**
         * Check indexer flag
         */
        $flag = Mage::getModel('catalogindex/catalog_index_flag')->loadSelf();
        if ($flag->getState() == Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_RUNNING) {
            /*if ($flag->getState() == Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_QUEUED)*/
            return $this;
        } else {
            $flag->setState(Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_RUNNING)->save();
        }

        try {
            /**
             * Collect initialization data
             */
            $websites = [];
            $attributeCodes = $priceAttributeCodes = [];

            /**
             * Prepare stores and websites information
             */
            if (is_null($stores)) {
                $stores     = $this->_getStores();
                $websites   = $this->_getWebsites();
            } elseif ($stores instanceof Mage_Core_Model_Store) {
                $websites[] = $stores->getWebsiteId();
                $stores     = [$stores];
            } elseif (is_array($stores)) {
                foreach ($stores as $one) {
                    $websites[] = Mage::app()->getStore($one)->getWebsiteId();
                }
            } elseif (!is_array($stores)) {
                Mage::throwException('Invalid stores supplied for indexing');
            }

            /**
             * Prepare attributes data
             */
            if (is_null($attributes)) {
                $priceAttributeCodes = $this->_indexers['price']->getIndexableAttributeCodes();
                $attributeCodes = $this->_indexers['eav']->getIndexableAttributeCodes();
            } elseif ($attributes instanceof Mage_Eav_Model_Entity_Attribute_Abstract) {
                if ($this->_indexers['eav']->isAttributeIndexable($attributes)) {
                    $attributeCodes[] = $attributes->getAttributeId();
                }
                if ($this->_indexers['price']->isAttributeIndexable($attributes)) {
                    $priceAttributeCodes[] = $attributes->getAttributeId();
                }
            } elseif ($attributes == self::REINDEX_TYPE_PRICE) {
                $priceAttributeCodes = $this->_indexers['price']->getIndexableAttributeCodes();
            } elseif ($attributes == self::REINDEX_TYPE_ATTRIBUTE) {
                $attributeCodes = $this->_indexers['eav']->getIndexableAttributeCodes();
            } else {
                Mage::throwException('Invalid attributes supplied for indexing');
            }

            /**
             * Delete index data
             */
            $this->_getResource()->clear(
                $attributeCodes,
                $priceAttributeCodes,
                count($priceAttributeCodes) > 0,
                count($priceAttributeCodes) > 0,
                count($priceAttributeCodes) > 0,
                $products,
                $stores,
            );

            /**
             * Process index price data per each website
             * (prices depends from website level)
             */
            foreach ($websites as $website) {
                $ws = Mage::app()->getWebsite($website);
                if (!$ws) {
                    continue;
                }

                $group = $ws->getDefaultGroup();
                if (!$group) {
                    continue;
                }

                $store = $group->getDefaultStore();

                /**
                 * It can happens when website with store was created but store view not yet
                 */
                if (!$store) {
                    continue;
                }

                foreach ($this->_getPriorifiedProductTypes() as $type) {
                    $collection = $this->_getProductCollection($store, $products);
                    $collection->addAttributeToFilter(
                        'status',
                        ['in' => Mage::getSingleton('catalog/product_status')->getSaleableStatusIds()],
                    );
                    $collection->addFieldToFilter('type_id', $type);
                    $this->_walkCollection($collection, $store, [], $priceAttributeCodes);
                    if (!is_null($products) && !$this->getRetreiver($type)->getTypeInstance()->isComposite()) {
                        $this->_walkCollectionRelation($collection, $ws, [], $priceAttributeCodes);
                    }
                }
            }

            /**
             * Process EAV attributes per each store view
             */
            foreach ($stores as $store) {
                foreach ($this->_getPriorifiedProductTypes() as $type) {
                    $collection = $this->_getProductCollection($store, $products);
                    Mage::getSingleton('catalog/product_visibility')->addVisibleInSiteFilterToCollection($collection);
                    $collection->addFieldToFilter('type_id', $type);

                    $this->_walkCollection($collection, $store, $attributeCodes);
                    if (!is_null($products) && !$this->getRetreiver($type)->getTypeInstance()->isComposite()) {
                        $this->_walkCollectionRelation($collection, $store, $attributeCodes);
                    }
                }
            }

            $this->_afterPlainReindex($stores, $products);

            /**
             * Catalog Product Flat price update
             */
            /** @var Mage_Catalog_Helper_Product_Flat $productFlatHelper */
            $productFlatHelper = Mage::helper('catalog/product_flat');
            if ($productFlatHelper->isAvailable() && $productFlatHelper->isBuilt()) {
                foreach ($stores as $store) {
                    $this->updateCatalogProductFlat($store, $products);
                }
            }
        } catch (Exception $e) {
            $flag->delete();
            throw $e;
        }

        if ($flag->getState() == Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_RUNNING) {
            $flag->delete();
        }

        return $this;
    }

    /**
     * After plain reindex process
     *
     * @param Mage_Core_Model_Store|array|int|Mage_Core_Model_Website $store
     * @param int|array|Mage_Catalog_Model_Product_Condition_Interface|Mage_Catalog_Model_Product $products
     * @return $this
     */
    protected function _afterPlainReindex($store, $products = null)
    {
        Mage::dispatchEvent('catalogindex_plain_reindex_after', [
            'products' => $products,
        ]);

        /**
         * Catalog Product Flat price update
         */
        /** @var Mage_Catalog_Helper_Product_Flat $productFlatHelper */
        $productFlatHelper = Mage::helper('catalog/product_flat');
        if ($productFlatHelper->isAvailable() && $productFlatHelper->isBuilt()) {
            if ($store instanceof Mage_Core_Model_Website) {
                foreach ($store->getStores() as $storeObject) {
                    $this->_afterPlainReindex($storeObject->getId(), $products);
                }
                return $this;
            } elseif ($store instanceof Mage_Core_Model_Store) {
                $store = $store->getId();
            } elseif (is_array($store)) { // array of stores
                foreach ($store as $storeObject) {
                    $this->_afterPlainReindex($storeObject->getId(), $products);
                }
                return $this;
            }

            $this->updateCatalogProductFlat($store, $products);
        }

        return $this;
    }

    /**
     * Return collection with product and store filters
     *
     * @param Mage_Core_Model_Store $store
     * @param mixed $products
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function _getProductCollection($store, $products)
    {
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->setStoreId($store)
            ->addStoreFilter($store);
        if ($products instanceof Mage_Catalog_Model_Product) {
            $collection->addIdFilter($products->getId());
        } elseif (is_array($products) || is_numeric($products)) {
            $collection->addIdFilter($products);
        } elseif ($products instanceof Mage_Catalog_Model_Product_Condition_Interface) {
            $products->applyToCollection($collection);
        }

        return $collection;
    }

    /**
     * Walk Product Collection for Relation Parent products
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website $store
     * @param array $attributes
     * @param array $prices
     * @return $this
     */
    public function _walkCollectionRelation($collection, $store, $attributes = [], $prices = [])
    {
        if ($store instanceof Mage_Core_Model_Website) {
            $storeObject = $store->getDefaultStore();
        } elseif ($store instanceof Mage_Core_Model_Store) {
            $storeObject = $store;
        }

        $statusCond = [
            'in' => Mage::getSingleton('catalog/product_status')->getSaleableStatusIds(),
        ];

        $productCount = $collection->getSize();
        $iterateCount = ($productCount / self::STEP_SIZE);
        for ($i = 0; $i < $iterateCount; $i++) {
            $stepData = $collection
                ->getAllIds(self::STEP_SIZE, $i * self::STEP_SIZE);
            foreach ($this->_getPriorifiedProductTypes() as $type) {
                $retriever = $this->getRetreiver($type);
                if (!$retriever->getTypeInstance()->isComposite()) {
                    continue;
                }

                $parentIds = $retriever->getTypeInstance()
                    ->getParentIdsByChild($stepData);
                if (isset($storeObject) && $parentIds) {
                    $parentCollection = $this->_getProductCollection($storeObject, $parentIds);
                    $parentCollection->addAttributeToFilter('status', $statusCond);
                    $parentCollection->addFieldToFilter('type_id', $type);
                    $this->_walkCollection($parentCollection, $storeObject, $attributes, $prices);

                    $this->_afterPlainReindex($store, $parentIds);
                }
            }
        }

        return $this;
    }

    /**
     * Run indexing process for product collection
     *
     * @param   Mage_Catalog_Model_Resource_Product_Collection $collection
     * @param   mixed $store
     * @param   array $attributes
     * @param   array $prices
     * @return  Mage_CatalogIndex_Model_Indexer
     */
    protected function _walkCollection($collection, $store, $attributes = [], $prices = [])
    {
        $productCount = $collection->getSize();
        if (!$productCount) {
            return $this;
        }

        for ($i = 0; $i < $productCount / self::STEP_SIZE; $i++) {
            $this->_getResource()->beginTransaction();
            try {
                $deleteKill = false;

                $stepData = $collection->getAllIds(self::STEP_SIZE, $i * self::STEP_SIZE);

                /**
                 * Reindex EAV attributes if required
                 */
                if (count($attributes)) {
                    $this->_getResource()->reindexAttributes($stepData, $attributes, $store);
                }

                /**
                 * Reindex prices if required
                 */
                if (count($prices)) {
                    $this->_getResource()->reindexPrices($stepData, $prices, $store);
                    $this->_getResource()->reindexTiers($stepData, $store);
                    $this->_getResource()->reindexMinimalPrices($stepData, $store);
                    $this->_getResource()->reindexFinalPrices($stepData, $store);
                }

                Mage::getResourceSingleton('catalog/product')->refreshEnabledIndex($store, $stepData);

                $kill = Mage::getModel('catalogindex/catalog_index_kill_flag')->loadSelf();
                if ($kill->checkIsThisProcess()) {
                    $this->_getResource()->rollBack();
                    $deleteKill = true;
                } else {
                    $this->_getResource()->commit();
                }
            } catch (Exception $e) {
                $this->_getResource()->rollBack();
                throw $e;
            }

            if ($deleteKill && isset($kill)) {
                $kill->delete();
            }
        }
        return $this;
    }

    /**
     * Retrieve Data retriever
     *
     * @param string $type
     * @return Mage_CatalogIndex_Model_Data_Abstract
     */
    public function getRetreiver($type)
    {
        return Mage::getSingleton('catalogindex/retreiver')->getRetreiver($type);
    }

    /**
     * Set CatalogIndex Flag as queue Indexing
     *
     * @return $this
     */
    public function queueIndexing()
    {
        Mage::getModel('catalogindex/catalog_index_flag')
            ->loadSelf()
            ->setState(Mage_CatalogIndex_Model_Catalog_Index_Flag::STATE_QUEUED)
            ->save();

        return $this;
    }

    /**
     * Get product types list by type priority
     * type priority is important in index process
     * example: before indexing complex (configurable, grouped etc.) products
     * we have to index all simple products
     *
     * @return array
     */
    protected function _getPriorifiedProductTypes()
    {
        if (is_null($this->_productTypePriority)) {
            $this->_productTypePriority = [];
            $config = Mage::getConfig()->getNode('global/catalog/product/type');

            foreach ($config->children() as $type) {
                $typeName = $type->getName();
                $typePriority = (string) $type->index_priority;
                $this->_productTypePriority[$typePriority] = $typeName;
            }
            ksort($this->_productTypePriority);
        }
        return $this->_productTypePriority;
    }

    /**
     * Retrieve Base to Specified Currency Rate
     *
     * @param string $code
     * @return double
     */
    protected function _getBaseToSpecifiedCurrencyRate($code)
    {
        return Mage::app()->getStore()->getBaseCurrency()->getRate($code);
    }

    /**
     * Build Entity price filter
     *
     * @param array $attributes
     * @param array $values
     * @param array $filteredAttributes
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return array
     */
    public function buildEntityPriceFilter($attributes, $values, &$filteredAttributes, $productCollection)
    {
        $additionalCalculations = [];
        $filter = [];
        $store = Mage::app()->getStore()->getId();
        $website = Mage::app()->getStore()->getWebsiteId();

        $currentStoreCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();

        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            if (isset($values[$code])) {
                foreach ($this->_priceIndexers as $indexerName) {
                    $indexer = $this->_indexers[$indexerName];
                    /** @var Mage_CatalogIndex_Model_Indexer_Abstract $indexer */
                    if ($indexer->isAttributeIndexable($attribute)) {
                        if ($values[$code]) {
                            if (isset($values[$code]['from']) && isset($values[$code]['to'])
                                && (strlen($values[$code]['from']) == 0 && strlen($values[$code]['to']) == 0)
                            ) {
                                continue;
                            }
                            $table = $indexer->getResource()->getMainTable();
                            if (!isset($filter[$code])) {
                                $filter[$code] = $this->_getSelect();
                                $filter[$code]->from($table, ['entity_id']);
                                $filter[$code]->distinct(true);

                                $response = new Varien_Object();
                                $response->setAdditionalCalculations([]);
                                $args = [
                                    'select' => $filter[$code],
                                    'table' => $table,
                                    'store_id' => $store,
                                    'response_object' => $response,
                                ];
                                Mage::dispatchEvent('catalogindex_prepare_price_select', $args);
                                $additionalCalculations[$code] = $response->getAdditionalCalculations();

                                if ($indexer->isAttributeIdUsed()) {
                                    //$filter[$code]->where("$table.attribute_id = ?", $attribute->getId());
                                }
                            }
                            if (is_array($values[$code])) {
                                $rateConversion = 1;
                                $filter[$code]->distinct(true);

                                if (isset($values[$code]['from']) && isset($values[$code]['to'])) {
                                    if (isset($values[$code]['currency'])) {
                                        $rateConversion = $this->_getBaseToSpecifiedCurrencyRate(
                                            $values[$code]['currency'],
                                        );
                                    } else {
                                        $rateConversion = $this->_getBaseToSpecifiedCurrencyRate($currentStoreCurrency);
                                    }

                                    if (strlen($values[$code]['from']) > 0) {
                                        $filter[$code]->where(
                                            "($table.min_price"
                                            . implode('', $additionalCalculations[$code]) . ")*{$rateConversion} >= ?",
                                            $values[$code]['from'],
                                        );
                                    }

                                    if (strlen($values[$code]['to']) > 0) {
                                        $filter[$code]->where(
                                            "($table.min_price"
                                            . implode('', $additionalCalculations[$code]) . ")*{$rateConversion} <= ?",
                                            $values[$code]['to'],
                                        );
                                    }
                                }
                            }
                            $filter[$code]->where("$table.website_id = ?", $website);

                            if ($code == 'price') {
                                $filter[$code]->where(
                                    $table . '.customer_group_id = ?',
                                    Mage::getSingleton('customer/session')->getCustomerGroupId(),
                                );
                            }

                            $filteredAttributes[] = $code;
                        }
                    }
                }
            }
        }
        return $filter;
    }

    /**
     * Build Entity filter
     *
     * @param array $attributes
     * @param array $values
     * @param array $filteredAttributes
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return array
     */
    public function buildEntityFilter($attributes, $values, &$filteredAttributes, $productCollection)
    {
        $filter = [];
        $store = Mage::app()->getStore()->getId();

        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            if (isset($values[$code])) {
                foreach ($this->_attributeIndexers as $indexerName) {
                    $indexer = $this->_indexers[$indexerName];
                    /** @var Mage_CatalogIndex_Model_Indexer_Abstract $indexer */
                    if ($indexer->isAttributeIndexable($attribute)) {
                        if ($values[$code]) {
                            if (isset($values[$code]['from']) && isset($values[$code]['to'])
                                && (!$values[$code]['from'] && !$values[$code]['to'])
                            ) {
                                continue;
                            }

                            $table = $indexer->getResource()->getMainTable();
                            if (!isset($filter[$code])) {
                                $filter[$code] = $this->_getSelect();
                                $filter[$code]->from($table, ['entity_id']);
                            }
                            if ($indexer->isAttributeIdUsed()) {
                                $filter[$code]->where('attribute_id = ?', $attribute->getId());
                            }
                            if (is_array($values[$code])) {
                                if (isset($values[$code]['from']) && isset($values[$code]['to'])) {
                                    if ($values[$code]['from']) {
                                        if (!is_numeric($values[$code]['from'])) {
                                            $_date = date(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT, strtotime($values[$code]['from']));
                                            $values[$code]['from'] = $_date;
                                        }

                                        $filter[$code]->where('value >= ?', $values[$code]['from']);
                                    }

                                    if ($values[$code]['to']) {
                                        if (!is_numeric($values[$code]['to'])) {
                                            $values[$code]['to'] = date(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT, strtotime($values[$code]['to']));
                                        }
                                        $filter[$code]->where('value <= ?', $values[$code]['to']);
                                    }
                                } else {
                                    $filter[$code]->where('value in (?)', $values[$code]);
                                }
                            } else {
                                $filter[$code]->where('value = ?', $values[$code]);
                            }
                            $filter[$code]->where('store_id = ?', $store);
                            $filteredAttributes[] = $code;
                        }
                    }
                }
            }
        }
        return $filter;
    }

    /**
     * Retrieve SELECT object
     *
     * @return Varien_Db_Select
     */
    protected function _getSelect()
    {
        return $this->_getResource()->getReadConnection()->select();
    }

    /**
     * Add indexable attributes to product collection select
     *
     * @deprecated
     * @param   Mage_Catalog_Model_Resource_Product_Collection $collection
     * @return  Mage_CatalogIndex_Model_Indexer
     */
    protected function _addFilterableAttributesToCollection($collection)
    {
        $attributeCodes = $this->_getIndexableAttributeCodes();
        foreach ($attributeCodes as $code) {
            $collection->addAttributeToSelect($code);
        }

        return $this;
    }

    /**
     * Prepare Catalog Product Flat Columns
     *
     * @return $this
     */
    public function prepareCatalogProductFlatColumns(Varien_Object $object)
    {
        $this->_getResource()->prepareCatalogProductFlatColumns($object);

        return $this;
    }

    /**
     * Prepare Catalog Product Flat Indexes
     *
     * @return $this
     */
    public function prepareCatalogProductFlatIndexes(Varien_Object $object)
    {
        $this->_getResource()->prepareCatalogProductFlatIndexes($object);

        return $this;
    }

    /**
     * Update price process for catalog product flat
     *
     * @param Mage_Core_Model_Store|int $store
     * @param Mage_Catalog_Model_Product|int|array|null $products
     * @param string $resourceTable
     * @return $this
     */
    public function updateCatalogProductFlat($store, $products = null, $resourceTable = null)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = $store->getId();
        }
        if ($products instanceof Mage_Catalog_Model_Product) {
            $products = $products->getId();
        }
        $this->_getResource()->updateCatalogProductFlat($store, $products, $resourceTable);

        return $this;
    }
}
