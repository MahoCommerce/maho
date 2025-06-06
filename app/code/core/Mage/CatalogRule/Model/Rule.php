<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog Rule data model
 *
 * @method Mage_CatalogRule_Model_Resource_Rule _getResource()
 * @method Mage_CatalogRule_Model_Resource_Rule getResource()
 * @method Mage_CatalogRule_Model_Resource_Rule_Collection getCollection()
 *
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method array getCollectedAttributes()
 * @method $this setCollectedAttributes(array $value)
 * @method string getDescription()
 * @method $this setDescription(string $value)
 * @method $this setDiscountAmount(float $value)
 * @method string getFromDate()
 * @method $this setFromDate(string $value)
 * @method string getName()
 * @method $this setName(string $value)
 * @method int getRuleId()
 * @method string getSimpleAction()
 * @method $this setSimpleAction(string $value)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $value)
 * @method int getStopRulesProcessing()
 * @method $this setStopRulesProcessing(int $value)
 * @method bool getSubIsEnable()
 * @method string getSubSimpleAction()
 * @method float getSubDiscountAmount()
 * @method string getToDate()
 * @method $this setToDate(string $value)
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
    protected $_eventPrefix = 'catalogrule_rule';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getRule() in this case
     *
     * @var string
     */
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
        $this->_factory = !empty($args['factory']) ? $args['factory'] : Mage::getSingleton('core/factory');
        $this->_config  = !empty($args['config']) ? $args['config'] : Mage::getConfig();
        $this->_app     = !empty($args['app']) ? $args['app'] : Mage::app();

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
            return Varien_Date::now();
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
                    [[$this, 'callbackValidateProduct']],
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
            $product = $this->_factory->getModel('catalog/product')->load($product);
        }
        if (is_null($websiteIds)) {
            $websiteIds = $this->getWebsiteIds();
        }
        $this->getResource()->applyToProduct($this, $product, $websiteIds);
        $this->getResource()->applyAllRules($product);
        $this->_invalidateCache();
    }

    /**
     * Apply all price rules, invalidate related cache and refresh price index
     *
     * @throws Exception
     */
    public function applyAll()
    {
        $this->getResourceCollection()->walk([$this->_getResource(), 'updateRuleProductData']);
        $this->_getResource()->applyAllRules();
        $this->_invalidateCache();
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
        $this->_invalidateCache();

        Mage::getSingleton('index/indexer')->processEntityAction(
            new Varien_Object(['id' => $product->getId()]),
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
        $dateTs     = Mage::app()->getLocale()->date()->getTimestamp();
        $cacheKey   = date('Y-m-d', $dateTs) . "|$websiteId|$customerGroupId|$productId|$price";

        if (!array_key_exists($cacheKey, self::$_priceRulesData)) {
            $rulesData = $this->_getResource()->getRulesFromProduct($dateTs, $websiteId, $customerGroupId, $productId);
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
            } else {
                self::$_priceRulesData[$cacheKey] = null;
            }
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
     * Invalidate related cache types
     *
     * @return $this
     */
    protected function _invalidateCache()
    {
        $types = $this->_config->getNode(self::XML_NODE_RELATED_CACHE);
        if ($types) {
            $types = $types->asArray();
            $this->_app->getCache()->invalidateType(array_keys($types));
        }
        return $this;
    }

    /**
     * @deprecated after 1.11.2.0
     *
     * @param string $format
     *
     * @return string
     */
    #[\Override]
    public function toString($format = '')
    {
        return '';
    }

    /**
     * Returns rule as an array for admin interface
     *
     * @deprecated after 1.11.2.0
     *
     * @param array $arrAttributes
     *
     * Output example:
     * array(
     *   'name'=>'Example rule',
     *   'conditions'=>{condition_combine::toArray}
     *   'actions'=>{action_collection::toArray}
     * )
     *
     * @return array
     */
    #[\Override]
    public function toArray(array $arrAttributes = [])
    {
        return parent::toArray($arrAttributes);
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
}
