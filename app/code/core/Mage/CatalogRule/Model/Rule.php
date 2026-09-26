<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

/**
 * Catalog Rule data model
 *
 * @method Mage_CatalogRule_Model_Resource_Rule _getResource()
 * @method Mage_CatalogRule_Model_Resource_Rule getResource()
 * @method Mage_CatalogRule_Model_Resource_Rule_Collection getCollection()
 */
class Mage_CatalogRule_Model_Rule extends Mage_Rule_Model_Abstract
{
    /**
     * Related cache types config path
     */
    public const XML_NODE_RELATED_CACHE = 'global/catalogrule/related_cache_types';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'catalogrule_rule';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getRule() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'rule';

    /**
     * Store matched product Ids
     *
     * @var array|null
     */
    protected $_productIds;

    /**
     * Limitation for products collection
     *
     * @var int|array|null
     */
    protected $_productsFilter = null;

    /**
     * Store current date at "Y-m-d H:i:s" format
     *
     * @var string
     */
    protected $_now;

    /**
     * Cached data of prices calculated by price rules
     *
     * @var array
     */
    protected static $_priceRulesData = [];

    /**
     * Factory instance
     *
     * @var Mage_Core_Model_Factory
     */
    protected $_factory = null;

    /**
     * Configuration object
     *
     * @var Mage_Core_Model_Config
     */
    protected $_config = null;

    /**
     * Configuration object
     *
     * @var Mage_Core_Model_App
     */
    protected $_app = null;

    /**
     * Constructor with parameters
     * Array of arguments with keys
     *  - 'factory' Mage_Core_Model_Factory
     *  - 'config' Mage_Core_Model_Config
     *  - 'app' Mage_Core_Model_App
     */
    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
        $this->_config  = empty($args['config']) ? Mage::getConfig() : $args['config'];
        $this->_app     = empty($args['app']) ? Mage::app() : $args['app'];

        parent::__construct();
    }

    /**
     * Init resource model and id field
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('catalogrule/rule');
        $this->setIdFieldName('rule_id');
    }

    /**
     * Getter for rule conditions collection
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Combine
     */
    #[\Override]
    public function getConditionsInstance()
    {
        return Mage::getModel('catalogrule/rule_condition_combine');
    }

    /**
     * Getter for rule actions collection
     *
     * @return Mage_CatalogRule_Model_Rule_Action_Collection
     */
    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('catalogrule/rule_action_collection');
    }

    /**
     * Get catalog rule customer group Ids
     *
     * @return array
     */
    public function getCustomerGroupIds()
    {
        if (!$this->hasCustomerGroupIds()) {
            $customerGroupIds = $this->_getResource()->getCustomerGroupIds($this->getId());
            $this->setData('customer_group_ids', (array) $customerGroupIds);
        }
        return $this->_getData('customer_group_ids');
    }

    /**
     * Retrieve current date for current rule
     *
     * @return string
     */
    public function getNow()
    {
        if (!$this->_now) {
            return Mage::app()->getLocale()->nowUtc();
        }
        return $this->_now;
    }

    /**
     * Set current date for current rule
     *
     * @param string $now
     */
    public function setNow($now)
    {
        $this->_now = $now;
    }

    /**
     * Get array of product ids which are matched by rule
     *
     * @return array
     */
    public function getMatchingProductIds()
    {
        if (is_null($this->_productIds)) {
            $this->_productIds = [];
            $this->setCollectedAttributes([]);

            if ($this->getWebsiteIds()) {
                $productCollection = Mage::getResourceModel('catalog/product_collection');
                $productCollection->addWebsiteFilter($this->getWebsiteIds());
                if ($this->_productsFilter) {
                    $productCollection->addIdFilter($this->_productsFilter);
                }
                $this->getConditions()->collectValidatedAttributes($productCollection);

                Mage::getSingleton('core/resource_iterator')->walk(
                    $productCollection->getSelect(),
                    [$this->callbackValidateProduct(...)],
                    [
                        'attributes' => $this->getCollectedAttributes(),
                        'product'    => Mage::getModel('catalog/product'),
                    ],
                );
            }
        }

        return $this->_productIds;
    }

    /**
     * Callback function for product matching
     *
     * @param array $args
     */
    public function callbackValidateProduct($args)
    {
        $product = clone $args['product'];
        $product->setData($args['row']);

        $results = [];
        foreach ($this->_getWebsitesMap() as $websiteId => $defaultStoreId) {
            $product->setStoreId($defaultStoreId);
            $results[$websiteId] = (int) $this->getConditions()->validate($product);
        }
        $this->_productIds[$product->getId()] = $results;
    }

    /**
     * Prepare website to default assigned store map
     *
     * @return array
     */
    protected function _getWebsitesMap()
    {
        $map = [];
        foreach ($this->_app->getWebsites(true) as $website) {
            if ($website->getDefaultStore()) {
                $map[$website->getId()] = $website->getDefaultStore()->getId();
            }
        }
        return $map;
    }

    /**
     * Apply rule to product
     *
     * @param int|Mage_Catalog_Model_Product $product
     * @param array|null $websiteIds
     */
    public function applyToProduct($product, $websiteIds = null)
    {
        if (is_numeric($product)) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = $this->_factory->getModel('catalog/product')->load($product);
        }
        $websiteIds ??= $this->getWebsiteIds();
        $this->getResource()->applyToProduct($this, $product, $websiteIds);
        $this->getResource()->applyAllRules($product);
        $this->_cleanProductCache($product);
    }

    /**
     * Apply all price rules, clean related cache and refresh price index
     *
     * @throws Exception
     */
    public function applyAll()
    {
        $this->getResourceCollection()->walk([$this->_getResource(), 'updateRuleProductData']);
        $this->_getResource()->applyAllRules();
        $this->_cleanCache();
        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
        if ($indexProcess) {
            $indexProcess->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
            $indexProcess->reindexAll();
        }
    }

    /**
     * Apply all price rules to product
     *
     * @param  int|Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function applyAllRulesToProduct($product)
    {
        if (is_numeric($product)) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->load($product);
        }

        $productWebsiteIds = $product->getWebsiteIds();

        /** @var Mage_CatalogRule_Model_Resource_Rule_Collection $rules */
        $rules = Mage::getModel('catalogrule/rule')->getCollection()
            ->addFieldToFilter('is_active', 1);
        if ($rules->count() === 0) {
            return $this;
        }
        foreach ($rules as $rule) {
            $websiteIds = array_intersect($productWebsiteIds, $rule->getWebsiteIds());
            $this->getResource()->applyToProduct($rule, $product, $websiteIds);
        }

        $this->getResource()->applyAllRules($product);
        $this->_cleanProductCache($product);

        Mage::getSingleton('index/indexer')->processEntityAction(
            new \Maho\DataObject(['id' => $product->getId()]),
            Mage_Catalog_Model_Product::ENTITY,
            Mage_Catalog_Model_Product_Indexer_Price::EVENT_TYPE_REINDEX_PRICE,
        );

        return $this;
    }

    /**
     * Calculate price using catalog price rule of product
     *
     * @param float $price
     * @return float|null
     */
    public function calcProductPriceRule(Mage_Catalog_Model_Product $product, $price)
    {
        $priceRules = null;
        $productId  = $product->getId();
        $storeId    = $product->getStoreId();
        $websiteId  = Mage::app()->getStore($storeId)->getWebsiteId();
        if ($product->hasCustomerGroupId()) {
            $customerGroupId = $product->getCustomerGroupId();
        } else {
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }
        $storeNow   = Mage::app()->getLocale()->utcToStore();
        $cacheKey   = $storeNow->format(Mage_Core_Model_Locale::DATE_FORMAT) . "|$websiteId|$customerGroupId|$productId|$price";

        if (!array_key_exists($cacheKey, self::$_priceRulesData)) {
            $rulesData = $this->_getResource()->getRulesFromProduct($storeNow->getTimestamp(), $websiteId, $customerGroupId, $productId);
            if ($rulesData) {
                foreach ($rulesData as $ruleData) {
                    if ($product->getParentId()) {
                        if (!empty($ruleData['sub_simple_action'])) {
                            $priceRules = Mage::helper('catalogrule')->calcPriceRule(
                                $ruleData['sub_simple_action'],
                                $ruleData['sub_discount_amount'],
                                $priceRules ?: $price,
                            );
                        } else {
                            $priceRules = ($priceRules ?: $price);
                        }
                        if ($ruleData['action_stop']) {
                            break;
                        }
                    } else {
                        $priceRules = Mage::helper('catalogrule')->calcPriceRule(
                            $ruleData['action_operator'],
                            $ruleData['action_amount'],
                            $priceRules ?: $price,
                        );
                        if ($ruleData['action_stop']) {
                            break;
                        }
                    }
                }
                return self::$_priceRulesData[$cacheKey] = $priceRules;
            }
            self::$_priceRulesData[$cacheKey] = null;
        } else {
            return self::$_priceRulesData[$cacheKey];
        }
        return null;
    }

    /**
     * Filtering products that must be checked for matching with rule
     *
     * @param  int|array $productIds
     */
    public function setProductsFilter($productIds)
    {
        $this->_productsFilter = $productIds;
    }

    /**
     * Returns products filter
     *
     * @return array|int|null
     */
    public function getProductsFilter()
    {
        return $this->_productsFilter;
    }

    /**
     * Clean related cache types
     *
     * @return $this
     */
    protected function _cleanCache()
    {
        $types = $this->_config->getNode(self::XML_NODE_RELATED_CACHE);
        if ($types) {
            foreach (array_keys($types->asArray()) as $type) {
                $this->_app->getCache()->cleanType($type);
            }
        }
        return $this;
    }

    /**
     * Clean the cache holding a single product's prices
     *
     * @return $this
     */
    protected function _cleanProductCache(Mage_Catalog_Model_Product $product)
    {
        $this->_app->cleanCache([Mage_Catalog_Model_Product::CACHE_TAG . '_' . $product->getId()]);
        return $this;
    }

    /**
     * Load matched product rules to the product
     *
     * @return $this
     */
    public function loadProductRules(Mage_Catalog_Model_Product $product)
    {
        if (!$product->hasData('matched_rules')) {
            $product->setMatchedRules($this->getResource()->getProductRuleIds($product->getId()));
        }
        return $this;
    }

    public function getCollectedAttributes(): ?array
    {
        return $this->getData('collected_attributes');
    }

    public function setCollectedAttributes(?array $value): static
    {
        return $this->setData('collected_attributes', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function getFromDate(): ?string
    {
        $value = $this->getData('from_date');
        return $value === null ? null : (string) $value;
    }

    public function setFromDate(?string $value): static
    {
        return $this->setData('from_date', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData('rule_id');
        return $value === null ? null : (int) $value;
    }

    public function getSimpleAction(): ?string
    {
        $value = $this->getData('simple_action');
        return $value === null ? null : (string) $value;
    }

    public function setSimpleAction(?string $value): static
    {
        return $this->setData('simple_action', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

    public function getStopRulesProcessing(): ?bool
    {
        $value = $this->getData('stop_rules_processing');
        return $value === null ? null : (bool) $value;
    }

    public function setStopRulesProcessing(?bool $value = true): static
    {
        return $this->setData('stop_rules_processing', $value);
    }

    public function getSubDiscountAmount(): ?float
    {
        $value = $this->getData('sub_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function getSubIsEnable(): ?bool
    {
        $value = $this->getData('sub_is_enable');
        return $value === null ? null : (bool) $value;
    }

    public function getSubSimpleAction(): ?string
    {
        $value = $this->getData('sub_simple_action');
        return $value === null ? null : (string) $value;
    }

    public function getToDate(): ?string
    {
        $value = $this->getData('to_date');
        return $value === null ? null : (string) $value;
    }

    public function setToDate(?string $value): static
    {
        return $this->setData('to_date', $value);
    }

}
