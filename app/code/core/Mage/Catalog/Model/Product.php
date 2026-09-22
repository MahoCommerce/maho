<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2015-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * Catalog product model
 *
 * @package    Mage_Catalog
 *
 * @method Mage_Catalog_Model_Resource_Product _getResource()
 * @method Mage_Catalog_Model_Resource_Product getResource()
 * @method Mage_Catalog_Model_Resource_Product_Collection getCollection()
 *
 * @method bool hasCategoryIds()
 * @method bool hasChildrenProducts()
 * @method bool hasConfigurableImagesFallbackArray()
 * @method bool hasCustomerGroupId()
 * @method bool hasCrossSellProducts()
 * @method bool hasCrossSellProductIds()
 * @method bool hasIsRecurring()
 * @method $this unsRecurringProfile()
 * @method bool hasMediaAttributes()
 * @method $this hasMsrpEnabled()
 * @method bool hasOptionsValidationFail()
 * @method bool hasPreconfiguredValues()
 * @method bool hasRelatedProducts()
 * @method bool hasRelatedProductIds()
 * @method $this unsSkipCheckRequiredOption()
 * @method bool hasStoreIds()
 * @method bool hasUpSellProducts()
 * @method bool hasUpSellProductIds()
 * @method bool hasUrlDataObject()
 * @method bool hasWebsiteIds()
 * @method bool hasWishlistStoreId()
 */
class Mage_Catalog_Model_Product extends Mage_Catalog_Model_Abstract
{
    /**
     * Entity code.
     * Can be used as part of method name for entity processing
     */
    public const ENTITY          = 'catalog_product';
    public const CACHE_TAG       = 'catalog_product';
    #[\Override]
    protected $_cacheTag         = 'catalog_product';
    #[\Override]
    protected $_eventPrefix      = 'catalog_product';
    #[\Override]
    protected $_eventObject      = 'product';
    protected $_canAffectOptions = false;

    /**
     * Product type instance
     *
     * @var Mage_Catalog_Model_Product_Type_Abstract|null|false
     */
    protected $_typeInstance            = null;

    /**
     * Product type instance as singleton
     */
    protected $_typeInstanceSingleton   = null;

    /**
     * Product link instance
     *
     * @var Mage_Catalog_Model_Product_Link|null
     */
    protected $_linkInstance;

    /**
     * Product object customization (not stored in DB)
     *
     * @var array
     */
    protected $_customOptions = [];

    /**
     * Product Url Instance
     *
     * @var Mage_Catalog_Model_Product_Url|null
     */
    protected $_urlModel = null;

    protected static $_url;
    protected static $_urlRewrite;

    protected $_errors = [];

    protected $_optionInstance;

    protected $_options = [];

    /**
     * Flag for available duplicate function
     *
     * @var bool
     */
    protected $_isDuplicable = true;

    /**
     * Flag for get Price function
     *
     * @var bool
     */
    protected $_calculatePrice = true;

    /**
     * @var Mage_CatalogInventory_Model_Stock_Item|null
     */
    protected $_stockItem;

    /**
     * @var Mage_Review_Model_Review_Summary[]
     */
    protected $_reviewSummary = [];

    /**
     * Initialize resources
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product');
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return \Maho\DataObject
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Retrieve Store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        if ($this->hasData('store_id')) {
            return (int) $this->getData('store_id');
        }
        return Mage::app()->getStore()->getId();
    }

    /**
     * Get collection instance
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    #[\Override]
    public function getResourceCollection()
    {
        if (empty($this->_resourceCollectionName)) {
            Mage::throwException(Mage::helper('catalog')->__('The model collection resource name is not defined.'));
        }
        $collection = Mage::getResourceModel($this->_resourceCollectionName);
        if (!$collection instanceof Mage_Catalog_Model_Resource_Product_Collection) {
            Mage::throwException(Mage::helper('catalog')->__('Invalid product collection resource.'));
        }
        $collection->setStoreId($this->getStoreId());
        return $collection;
    }

    /**
     * Get product url model
     *
     * @return Mage_Catalog_Model_Product_Url
     */
    public function getUrlModel()
    {
        $this->_urlModel ??= Mage::getSingleton('catalog/factory')->getProductUrlInstance();
        return $this->_urlModel;
    }

    /**
     * Validate Product Data
     *
     * @todo implement full validation process with errors returning which are ignoring now
     *
     * @return $this
     */
    public function validate()
    {
        Mage::dispatchEvent($this->_eventPrefix . '_validate_before', [$this->_eventObject => $this]);
        $this->_getResource()->validate($this);
        Mage::dispatchEvent($this->_eventPrefix . '_validate_after', [$this->_eventObject => $this]);
        return $this;
    }

    /**
     * Get product name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->_getData('name');
    }

    /**
     * Get product price through type instance
     *
     * @return float|null
     */
    public function getPrice()
    {
        if ($this->_calculatePrice || !$this->getData('price')) {
            return $this->getPriceModel()->getPrice($this);
        }
        return $this->getPriceAttributeValue('price');
    }

    public function getPriceAttributeValue(string $code): ?float
    {
        $value = $this->_getData($code);
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $value = (float) $value;

        if ($this->getExistsStoreValueFlag($code)) {
            return $value;
        }

        $rate = $this->getWebsitePriceRate($code);

        return $rate === null ? null : round($value * $rate, 4);
    }

    public function getWebsitePriceRate(string $code = 'price'): ?float
    {
        $attribute = $this->getResource()->getAttribute($code);
        if (!$attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute || $attribute->isScopeGlobal()) {
            return 1.0;
        }

        return Mage::helper('catalog')->getWebsitePriceRate($this->getPriceStoreId());
    }

    /**
     * The store the prices on this product were loaded for, which a collection has to say
     * explicitly because its items carry no store of their own.
     */
    public function getPriceStoreId(): int
    {
        return (int) ($this->_getData('price_store_id') ?? $this->getStoreId());
    }

    /**
     * Set Price calculation flag
     *
     * @param bool $calculate
     * @return $this
     */
    public function setPriceCalculation($calculate = true)
    {
        $this->_calculatePrice = $calculate;
        return $this;
    }

    /**
     * Get product type identifier
     *
     * @return string|null
     */
    public function getTypeId()
    {
        return $this->_getData('type_id');
    }

    /**
     * Get product status
     *
     * @return int
     */
    public function getStatus()
    {
        if (is_null($this->_getData('status'))) {
            $this->setData('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        }
        return $this->_getData('status');
    }

    /**
     * Retrieve type instance
     *
     * Type instance implement type depended logic
     *
     * @param  bool $singleton
     * @return Mage_Catalog_Model_Product_Type_Abstract
     */
    public function getTypeInstance($singleton = false)
    {
        if ($singleton === true) {
            $this->_typeInstanceSingleton ??= Mage::getSingleton('catalog/product_type')
                ->factory($this, true);
            return $this->_typeInstanceSingleton;
        }

        $this->_typeInstance ??= Mage::getSingleton('catalog/product_type')
            ->factory($this);
        return $this->_typeInstance;
    }

    /**
     * Set type instance for external
     *
     * @param Mage_Catalog_Model_Product_Type_Abstract $instance  Product type instance
     * @param bool                                     $singleton Whether instance is singleton
     * @return $this
     */
    public function setTypeInstance($instance, $singleton = false)
    {
        if ($singleton === true) {
            $this->_typeInstanceSingleton = $instance;
        } else {
            $this->_typeInstance = $instance;
        }
        return $this;
    }

    /**
     * Retrieve link instance
     *
     * @return  Mage_Catalog_Model_Product_Link
     */
    public function getLinkInstance()
    {
        if (!$this->_linkInstance) {
            $this->_linkInstance = Mage::getSingleton('catalog/product_link');
        }
        return $this->_linkInstance;
    }

    /**
     * Retrieve product id by sku
     *
     * @param   string $sku
     * @return  string
     */
    public function getIdBySku($sku)
    {
        return $this->_getResource()->getIdBySku($sku);
    }

    /**
     * Retrieve product category id
     *
     * @return int|false
     */
    public function getCategoryId()
    {
        if ($category = Mage::registry('current_category')) {
            return $category->getId();
        }
        return false;
    }

    /**
     * Retrieve product category
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        $category = $this->getData('category');
        if (is_null($category) && $this->getCategoryId()) {
            $category = Mage::getModel('catalog/category')->load($this->getCategoryId());
            $this->setCategory($category);
        }
        return $category;
    }

    /**
     * Set assigned category IDs array to product
     *
     * @param array|int|string $ids the ID(s) as int, comma-separated string or array of ints
     * @return $this
     */
    public function setCategoryIds($ids)
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        } elseif (is_int($ids)) {
            $ids = (array) $ids;
        } elseif (!is_array($ids)) {
            Mage::throwException(Mage::helper('catalog')->__('Invalid category IDs.'));
        }
        $ids = array_filter(array_map(\intval(...), $ids));
        $this->setData('category_ids', $ids);
        return $this;
    }

    /**
     * Retrieve assigned category Ids
     *
     * @return array
     */
    public function getCategoryIds()
    {
        if (!$this->hasData('category_ids')) {
            $wasLocked = false;
            if ($this->isLockedAttribute('category_ids')) {
                $wasLocked = true;
                $this->unlockAttribute('category_ids');
            }
            $ids = $this->_getResource()->getCategoryIds($this);
            $this->setData('category_ids', $ids);
            if ($wasLocked) {
                $this->lockAttribute('category_ids');
            }
        }

        return (array) $this->_getData('category_ids');
    }

    /**
     * Retrieve product categories
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getCategoryCollection()
    {
        return $this->_getResource()->getCategoryCollection($this);
    }

    /**
     * Retrieve product websites identifiers
     *
     * @return array
     */
    public function getWebsiteIds()
    {
        if (!$this->hasWebsiteIds()) {
            $ids = $this->_getResource()->getWebsiteIds($this);
            $this->setWebsiteIds($ids);
        }
        return $this->getData('website_ids');
    }

    /**
     * Get all sore ids where product is presented
     *
     * @return array
     */
    public function getStoreIds()
    {
        if (!$this->hasStoreIds()) {
            $storeIds = [];
            if ($websiteIds = $this->getWebsiteIds()) {
                foreach ($websiteIds as $websiteId) {
                    $websiteStores = Mage::app()->getWebsite($websiteId)->getStoreIds();
                    $storeIds = array_merge($storeIds, $websiteStores);
                }
            }
            $this->setStoreIds($storeIds);
        }
        return $this->getData('store_ids');
    }

    /**
     * Retrieve product attributes
     * if $groupId is null - retrieve all product attributes
     *
     * @param int  $groupId   Retrieve attributes of the specified group
     * @param bool $skipSuper Not used
     * @return array
     */
    public function getAttributes($groupId = null, $skipSuper = false)
    {
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute[] $productAttributes */
        $productAttributes = $this->getTypeInstance(true)->getEditableAttributes($this);
        if ($groupId) {
            $attributes = [];
            foreach ($productAttributes as $attribute) {
                if ($attribute->isInGroup($this->getAttributeSetId(), $groupId)) {
                    $attributes[] = $attribute;
                }
            }
        } else {
            $attributes = $productAttributes;
        }

        return $attributes;
    }

    /**
     * @return string
     */
    public function getLinksTitle()
    {
        return (string) $this->_getData('links_title');
    }

    /**
     * @return Mage_CatalogInventory_Model_Stock_Item
     */
    public function getStockItem()
    {
        return $this->_stockItem;
    }

    /**
     * @return bool
     */
    public function hasStockItem()
    {
        return (bool) $this->_stockItem;
    }

    /**
     * @param Mage_CatalogInventory_Model_Stock_Item $stockItem
     * @return $this
     */
    public function setStockItem($stockItem)
    {
        $this->_stockItem = $stockItem;
        return $this;
    }

    /**
     * Check product options and type options and save them, too
     *
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->cleanCache();
        $this->setTypeHasOptions(false);
        $this->setTypeHasRequiredOptions(false);

        $this->getTypeInstance(true)->beforeSave($this);

        $hasOptions         = false;
        $hasRequiredOptions = false;

        /**
         * $this->_canAffectOptions - set by type instance only
         * $this->getCanSaveCustomOptions() - set either in controller when "Custom Options" ajax tab is loaded,
         * or in type instance as well
         */
        $this->canAffectOptions($this->_canAffectOptions && $this->getCanSaveCustomOptions());
        if ($this->getCanSaveCustomOptions()) {
            $options = $this->getProductOptions();
            if (is_array($options)) {
                $this->setIsCustomOptionChanged();
                foreach ($this->getProductOptions() as $option) {
                    $this->getOptionInstance()->addOption($option);
                    if ((!isset($option['is_delete'])) || $option['is_delete'] != '1') {
                        if (!empty($option['file_extension'])) {
                            $fileExtension = $option['file_extension'];
                            if (strcmp($fileExtension, Mage::helper('core')->removeTags($fileExtension)) !== 0) {
                                Mage::throwException(Mage::helper('catalog')->__('Invalid custom option(s).'));
                            }
                        }
                        $hasOptions = true;
                    }
                }
                foreach ($this->getOptionInstance()->getOptions() as $option) {
                    if ($option['is_require'] == '1') {
                        $hasRequiredOptions = true;
                        break;
                    }
                }
            }
        }

        /**
         * Set true, if any
         * Set false, ONLY if options have been affected by Options tab and Type instance tab
         */
        if ($hasOptions || (bool) $this->getTypeHasOptions()) {
            $this->setHasOptions();
            if ($hasRequiredOptions || (bool) $this->getTypeHasRequiredOptions()) {
                $this->setRequiredOptions();
            } elseif ($this->canAffectOptions()) {
                $this->setRequiredOptions(false);
            }
        } elseif ($this->canAffectOptions()) {
            $this->setHasOptions(false);
            $this->setRequiredOptions(false);
        }
        return parent::_beforeSave();
    }

    /**
     * Check/set if options can be affected when saving product
     * If value specified, it will be set.
     *
     * @param   bool $value
     * @return  bool
     */
    public function canAffectOptions($value = null)
    {
        if ($value !== null) {
            $this->_canAffectOptions = (bool) $value;
        }
        return $this->_canAffectOptions;
    }

    /**
     * Saving product type related data and init index
     */
    #[\Override]
    protected function _afterSave()
    {
        $this->getLinkInstance()->saveProductRelations($this);
        $this->getTypeInstance(true)->save($this);

        /**
         * Product Options
         */
        $this->getOptionInstance()->setProduct($this)
            ->saveOptions();

        return parent::_afterSave();
    }

    /**
     * Clear cache related with product and protect delete from not admin
     * Register indexing event before delete product
     */
    #[\Override]
    protected function _beforeDelete()
    {
        $this->_protectFromNonAdmin();
        $this->cleanCache();

        return parent::_beforeDelete();
    }

    /**
     * Init indexing process after product delete commit
     */
    #[\Override]
    protected function _afterDeleteCommit()
    {
        parent::_afterDeleteCommit();

        /** @var \Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getSingleton('index/indexer');

        $indexer->processEntityAction($this, self::ENTITY, Mage_Index_Model_Event::TYPE_DELETE);
        return $this;
    }

    /**
     * Load product options if they exists
     *
     * @return $this
     */
    #[\Override]
    protected function _afterLoad()
    {
        parent::_afterLoad();
        /**
         * Load product options
         */
        if ($this->getHasOptions()) {
            foreach ($this->getProductOptionsCollection() as $option) {
                $option->setProduct($this);
                $this->addOption($option);
            }
        }
        return $this;
    }

    /**
     * Clear cache related with product id
     *
     * @return $this
     */
    public function cleanCache()
    {
        if ($this->getId()) {
            Mage::app()->cleanCache('catalog_product_' . $this->getId());
        }
        return $this;
    }

    /**
     * Get product price model
     *
     * @return Mage_Bundle_Model_Product_Price
     */
    public function getPriceModel()
    {
        return Mage::getSingleton('catalog/product_type')->priceFactory($this->getTypeId());
    }

    /**
     * Get product group price
     *
     * @return float
     */
    public function getGroupPrice()
    {
        return $this->getPriceModel()->getGroupPrice($this);
    }

    /**
     * Get product tier price by qty
     *
     * @param   float $qty
     * @return  float|array
     */
    public function getTierPrice($qty = null)
    {
        return $this->getPriceModel()->getTierPrice($qty, $this);
    }

    /**
     * Count how many tier prices we have for the product
     *
     * @return  int
     */
    public function getTierPriceCount()
    {
        return $this->getPriceModel()->getTierPriceCount($this);
    }

    /**
     * Get formatted by currency tier price
     *
     * @param   double $qty
     * @return  array | double
     */
    public function getFormatedTierPrice($qty = null)
    {
        return $this->getPriceModel()->getFormatedTierPrice($qty, $this);
    }

    /**
     * Get formatted by currency product price
     *
     * @return  array|double
     */
    public function getFormatedPrice()
    {
        return $this->getPriceModel()->getFormatedPrice($this);
    }

    /**
     * Sets final price of product
     *
     * This func is equal to magic 'setFinalPrice()', but added as a separate func, because in cart with bundle
     * products it's called very often in Item->getProduct(). So removing chain of magic with more cpu consuming
     * algorithms gives nice optimization boost.
     *
     * @param float|null $price Price amount
     * @return $this
     */
    public function setFinalPrice($price)
    {
        $this->_data['final_price'] = $price;
        return $this;
    }

    /**
     * Get product final price
     *
     * @param double $qty
     * @return double
     */
    public function getFinalPrice($qty = null)
    {
        $price = $this->_getData('final_price');
        return $price ?? $this->getPriceModel()->getFinalPrice($qty, $this);
    }

    /**
     * Returns calculated final price
     *
     * @return float|null
     */
    public function getCalculatedFinalPrice()
    {
        return $this->_getData('calculated_final_price');
    }

    /**
     * Returns minimal price
     *
     * @return float
     */
    public function getMinimalPrice()
    {
        return max($this->_getData('minimal_price'), 0);
    }

    /**
     * Returns special price with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getSpecialPrice(): ?float
    {
        if (!$this->getPriceModel()->isSpecialPriceFixed()) {
            $value = $this->_getData('special_price');
            return $value !== null ? (float) $value : null;
        }

        return $this->getPriceAttributeValue('special_price');
    }

    public function getMsrp(): ?float
    {
        return $this->getPriceAttributeValue('msrp');
    }

    /**
     * Returns starting date of the special price
     *
     * @return mixed
     */
    public function getSpecialFromDate()
    {
        return $this->_getData('special_from_date');
    }

    /**
     * Returns end date of the special price
     *
     * @return mixed
     */
    public function getSpecialToDate()
    {
        return $this->_getData('special_to_date');
    }

    /*******************************************************************************
     ** Linked products API
     */
    /**
     * Retrieve array of related roducts
     *
     * @return Mage_Catalog_Model_Product[]
     */
    public function getRelatedProducts()
    {
        if (!$this->hasRelatedProducts()) {
            $products = [];
            $collection = $this->getRelatedProductCollection();
            foreach ($collection as $product) {
                $products[] = $product;
            }
            $this->setRelatedProducts($products);
        }
        return $this->getData('related_products');
    }

    /**
     * Retrieve related products identifiers
     *
     * @return array
     */
    public function getRelatedProductIds()
    {
        if (!$this->hasRelatedProductIds()) {
            $ids = [];
            foreach ($this->getRelatedProducts() as $product) {
                $ids[] = $product->getId();
            }
            $this->setRelatedProductIds($ids);
        }
        return $this->getData('related_product_ids');
    }

    /**
     * Retrieve collection related product
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Product_Collection
     */
    public function getRelatedProductCollection()
    {
        $collection = $this->getLinkInstance()->useRelatedLinks()
            ->getProductCollection()
            ->setIsStrongMode();
        $collection->setProduct($this);
        return $collection;
    }

    /**
     * Retrieve collection related link
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Collection
     */
    public function getRelatedLinkCollection()
    {
        $collection = $this->getLinkInstance()->useRelatedLinks()
            ->getLinkCollection();
        $collection->setProduct($this);
        $collection->addLinkTypeIdFilter();
        $collection->addProductIdFilter();
        $collection->joinAttributes();
        return $collection;
    }

    /**
     * Retrieve array of up sell products
     *
     * @return Mage_Catalog_Model_Product[]
     */
    public function getUpSellProducts()
    {
        if (!$this->hasUpSellProducts()) {
            $products = [];
            foreach ($this->getUpSellProductCollection() as $product) {
                $products[] = $product;
            }
            $this->setUpSellProducts($products);
        }
        return $this->getData('up_sell_products');
    }

    /**
     * Retrieve up sell products identifiers
     *
     * @return array
     */
    public function getUpSellProductIds()
    {
        if (!$this->hasUpSellProductIds()) {
            $ids = [];
            foreach ($this->getUpSellProducts() as $product) {
                $ids[] = $product->getId();
            }
            $this->setUpSellProductIds($ids);
        }
        return $this->getData('up_sell_product_ids');
    }

    /**
     * Retrieve collection up sell product
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Product_Collection
     */
    public function getUpSellProductCollection()
    {
        $collection = $this->getLinkInstance()->useUpSellLinks()
            ->getProductCollection()
            ->setIsStrongMode();
        $collection->setProduct($this);
        return $collection;
    }

    /**
     * Retrieve collection up sell link
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Collection
     */
    public function getUpSellLinkCollection()
    {
        $collection = $this->getLinkInstance()->useUpSellLinks()
            ->getLinkCollection();
        $collection->setProduct($this);
        $collection->addLinkTypeIdFilter();
        $collection->addProductIdFilter();
        $collection->joinAttributes();
        return $collection;
    }

    /**
     * Retrieve array of cross sell products
     *
     * @return array
     */
    public function getCrossSellProducts()
    {
        if (!$this->hasCrossSellProducts()) {
            $products = [];
            foreach ($this->getCrossSellProductCollection() as $product) {
                $products[] = $product;
            }
            $this->setCrossSellProducts($products);
        }
        return $this->getData('cross_sell_products');
    }

    /**
     * Retrieve cross sell products identifiers
     *
     * @return array
     */
    public function getCrossSellProductIds()
    {
        if (!$this->hasCrossSellProductIds()) {
            $ids = [];
            foreach ($this->getCrossSellProducts() as $product) {
                $ids[] = $product->getId();
            }
            $this->setCrossSellProductIds($ids);
        }
        return $this->getData('cross_sell_product_ids');
    }

    /**
     * Retrieve collection cross sell product
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Product_Collection
     */
    public function getCrossSellProductCollection()
    {
        $collection = $this->getLinkInstance()->useCrossSellLinks()
            ->getProductCollection()
            ->setIsStrongMode();
        $collection->setProduct($this);
        return $collection;
    }

    /**
     * Retrieve collection cross sell link
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Collection
     */
    public function getCrossSellLinkCollection()
    {
        $collection = $this->getLinkInstance()->useCrossSellLinks()
            ->getLinkCollection();
        $collection->setProduct($this);
        $collection->addLinkTypeIdFilter();
        $collection->addProductIdFilter();
        $collection->joinAttributes();
        return $collection;
    }

    /**
     * Retrieve collection grouped link
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Collection
     */
    public function getGroupedLinkCollection()
    {
        $collection = $this->getLinkInstance()->useGroupedLinks()
            ->getLinkCollection();
        $collection->setProduct($this);
        $collection->addLinkTypeIdFilter();
        $collection->addProductIdFilter();
        $collection->joinAttributes();
        return $collection;
    }

    /*******************************************************************************
     ** Media API
     */
    /**
     * Retrieve attributes for media gallery
     *
     * @return Mage_Catalog_Model_Resource_Eav_Attribute[]
     */
    public function getMediaAttributes()
    {
        if (!$this->hasMediaAttributes()) {
            $mediaAttributes = [];
            foreach ($this->getAttributes() as $attribute) {
                if ($attribute->getFrontend()->getInputType() == 'media_image') {
                    $mediaAttributes[$attribute->getAttributeCode()] = $attribute;
                }
            }
            $this->setMediaAttributes($mediaAttributes);
        }
        return $this->getData('media_attributes');
    }

    /**
     * Retrieve media gallery images
     *
     * @return \Maho\Data\Collection
     */
    public function getMediaGalleryImages()
    {
        $gallery = $this->getMediaGallery();
        if (!$this->hasData('media_gallery_images') && is_array($gallery['images'] ?? null)) {
            $images = new \Maho\Data\Collection();
            foreach ($gallery['images'] as $image) {
                if ($image['disabled']) {
                    continue;
                }
                $image['url'] = $this->getMediaConfig()->getMediaUrl($image['file']);
                $image['id'] = $image['value_id'] ?? null;
                $image['path'] = $this->getMediaConfig()->getMediaPath($image['file']);
                $images->addItem(new \Maho\DataObject($image));
            }
            $this->setData('media_gallery_images', $images);
        }

        return $this->getData('media_gallery_images');
    }

    /**
     * Add image to media gallery
     *
     * @param string        $file              file path of image in file system
     * @param string|array  $mediaAttribute    code of attribute with type 'media_image',
     *                                          leave blank if image should be only in gallery
     * @param bool       $move              if true, it will move source file
     * @param bool       $exclude           mark image as disabled in product page view
     * @return $this
     */
    public function addImageToMediaGallery($file, $mediaAttribute = null, $move = false, $exclude = true)
    {
        $attributes = $this->getTypeInstance(true)->getSetAttributes($this);
        if (!isset($attributes['media_gallery'])) {
            return $this;
        }
        $mediaGalleryAttribute = $attributes['media_gallery'];
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $mediaGalleryAttribute */
        $mediaGalleryAttribute->getBackend()->addImage($this, $file, $mediaAttribute, $move, $exclude);
        return $this;
    }

    /**
     * Retrieve product media config
     *
     * @return Mage_Catalog_Model_Product_Media_Config
     */
    public function getMediaConfig()
    {
        return Mage::getSingleton('catalog/product_media_config');
    }

    /**
     * Create duplicate
     *
     * @return Mage_Catalog_Model_Product
     */
    public function duplicate(bool $duplicateImages = true)
    {
        $this->getWebsiteIds();
        $this->getCategoryIds();

        /** @var Mage_Catalog_Model_Product $newProduct */
        $newProduct = Mage::getModel('catalog/product')->setData($this->getData())
            ->setIsDuplicate()
            ->setDuplicateImages($duplicateImages)
            ->setOriginalId($this->getId())
            ->setSku(null)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
            ->setCreatedAt(null)
            ->setUpdatedAt(null)
            ->setId(null)
            ->setStoreId(Mage::app()->getStore()->getId());

        // Clear image data if not duplicating images
        if (!$duplicateImages) {
            $newProduct->setMediaGallery(['images' => [], 'values' => []]);
            foreach ($newProduct->getMediaAttributes() as $mediaAttribute) {
                $newProduct->setData($mediaAttribute->getAttributeCode());
            }
        }

        Mage::dispatchEvent(
            'catalog_model_product_duplicate',
            ['current_product' => $this, 'new_product' => $newProduct],
        );

        /* Prepare Related*/
        $data = [];
        $this->getLinkInstance()->useRelatedLinks();
        $attributes = [];
        foreach ($this->getLinkInstance()->getAttributes() as $_attribute) {
            if (isset($_attribute['code'])) {
                $attributes[] = $_attribute['code'];
            }
        }
        foreach ($this->getRelatedLinkCollection() as $_link) {
            $data[$_link->getLinkedProductId()] = $_link->toArray($attributes);
        }
        $newProduct->setRelatedLinkData($data);

        /* Prepare UpSell*/
        $data = [];
        $this->getLinkInstance()->useUpSellLinks();
        $attributes = [];
        foreach ($this->getLinkInstance()->getAttributes() as $_attribute) {
            if (isset($_attribute['code'])) {
                $attributes[] = $_attribute['code'];
            }
        }
        foreach ($this->getUpSellLinkCollection() as $_link) {
            $data[$_link->getLinkedProductId()] = $_link->toArray($attributes);
        }
        $newProduct->setUpSellLinkData($data);

        /* Prepare Cross Sell */
        $data = [];
        $this->getLinkInstance()->useCrossSellLinks();
        $attributes = [];
        foreach ($this->getLinkInstance()->getAttributes() as $_attribute) {
            if (isset($_attribute['code'])) {
                $attributes[] = $_attribute['code'];
            }
        }
        foreach ($this->getCrossSellLinkCollection() as $_link) {
            $data[$_link->getLinkedProductId()] = $_link->toArray($attributes);
        }
        $newProduct->setCrossSellLinkData($data);

        /* Prepare Grouped */
        $data = [];
        $this->getLinkInstance()->useGroupedLinks();
        $attributes = [];
        foreach ($this->getLinkInstance()->getAttributes() as $_attribute) {
            if (isset($_attribute['code'])) {
                $attributes[] = $_attribute['code'];
            }
        }
        foreach ($this->getGroupedLinkCollection() as $_link) {
            $data[$_link->getLinkedProductId()] = $_link->toArray($attributes);
        }
        $newProduct->setGroupedLinkData($data);

        $newProduct->save();

        $this->getOptionInstance()->duplicate($this->getId(), $newProduct->getId());
        $this->getResource()->duplicate($this->getId(), $newProduct->getId());

        // TODO - duplicate product on all stores of the websites it is associated with
        /*if ($storeIds = $this->getWebsiteIds()) {
            foreach ($storeIds as $storeId) {
                $this->setStoreId($storeId)
                   ->load($this->getId());

                $newProduct->setData($this->getData())
                    ->setSku(null)
                    ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
                    ->setId($newId)
                    ->save();
            }
        }*/
        return $newProduct;
    }

    /**
     * Is product grouped
     *
     * @return bool
     */
    public function isSuperGroup()
    {
        return $this->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED;
    }

    /**
     * Alias for isConfigurable()
     *
     * @return bool
     */
    public function isSuperConfig()
    {
        return $this->isConfigurable();
    }
    /**
     * Check is product grouped
     *
     * @return bool
     */
    public function isGrouped()
    {
        return $this->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED;
    }

    /**
     * Check is product configurable
     *
     * @return bool
     */
    public function isConfigurable()
    {
        return $this->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
    }

    /**
     * Whether product configurable or grouped
     *
     * @return bool
     */
    public function isSuper()
    {
        return $this->isConfigurable() || $this->isGrouped();
    }

    /**
     * Returns visible status IDs in catalog
     *
     * @return array
     */
    public function getVisibleInCatalogStatuses()
    {
        return Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();
    }

    /**
     * Retrieve visible statuses
     *
     * @return array
     */
    public function getVisibleStatuses()
    {
        return Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();
    }

    /**
     * Check Product visilbe in catalog
     *
     * @return bool
     */
    public function isVisibleInCatalog()
    {
        return in_array($this->getStatus(), $this->getVisibleInCatalogStatuses());
    }

    /**
     * Retrieve visible in site visibilities
     *
     * @return array
     */
    public function getVisibleInSiteVisibilities()
    {
        return Mage_Catalog_Model_Product_Visibility::getVisibleInSiteIds();
    }

    /**
     * Check Product visible in site
     *
     * @return bool
     */
    public function isVisibleInSiteVisibility()
    {
        return in_array($this->getVisibility(), $this->getVisibleInSiteVisibilities());
    }

    /**
     * Checks product can be duplicated
     *
     * @return bool
     */
    public function isDuplicable()
    {
        return $this->_isDuplicable;
    }

    /**
     * Set is duplicable flag
     *
     * @param bool $value
     * @return $this
     */
    public function setIsDuplicable($value)
    {
        $this->_isDuplicable = (bool) $value;
        return $this;
    }

    /**
     * Check is product available for sale
     *
     * @return bool
     */
    public function isSalable()
    {
        Mage::dispatchEvent('catalog_product_is_salable_before', [
            'product'   => $this,
        ]);

        $salable = $this->isAvailable();

        if ($salable && $this->getWebsitePriceRate() === null) {
            $salable = false;
        }

        $object = new \Maho\DataObject([
            'product'    => $this,
            'is_salable' => $salable,
        ]);
        Mage::dispatchEvent('catalog_product_is_salable_after', [
            'product'   => $this,
            'salable'   => $object,
        ]);
        return $object->getIsSalable();
    }

    /**
     * Check whether the product type or stock allows to purchase the product
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->getTypeInstance(true)->isSalable($this)
            || Mage::helper('catalog/product')->getSkipSaleableCheck();
    }

    /**
     * Is product salable detecting by product type
     *
     * @return bool
     */
    public function getIsSalable()
    {
        $productType = $this->getTypeInstance(true);
        if (method_exists($productType, 'getIsSalable')) {
            return $productType->getIsSalable($this);
        }
        if ($this->hasData('is_salable')) {
            return $this->getData('is_salable');
        }

        return $this->isSalable();
    }

    /**
     * Check is a virtual product
     * Data helper wrapper
     *
     * @return bool
     */
    public function isVirtual()
    {
        return $this->getIsVirtual();
    }

    /**
     * Whether the product is a recurring payment
     *
     * @return bool
     */
    public function isRecurring()
    {
        return $this->getIsRecurring() == '1';
    }

    /**
     * Alias for isSalable()
     *
     * @return bool
     */
    public function isSaleable()
    {
        return $this->isSalable();
    }

    /**
     * Whether product available in stock
     *
     * @return bool
     */
    public function isInStock()
    {
        return $this->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
    }

    /**
     * Get attribute text by its code
     *
     * @param string $attributeCode of the attribute
     * @return string
     */
    public function getAttributeText($attributeCode)
    {
        return $this->getResource()
            ->getAttribute($attributeCode)
                ->getSource()
                    ->getOptionText($this->getData($attributeCode));
    }

    /**
     * Returns array with dates for custom design
     *
     * @return array
     */
    public function getCustomDesignDate()
    {
        $result = [];
        $result['from'] = $this->getData('custom_design_from');
        $result['to'] = $this->getData('custom_design_to');

        return $result;
    }

    /**
     * Retrieve Product URL
     *
     * @return string
     */
    public function getProductUrl()
    {
        return $this->getUrlModel()->getProductUrl($this);
    }

    /**
     * Retrieve URL in current store
     *
     * @param array $params the route params
     * @return string
     */
    public function getUrlInStore($params = [])
    {
        return $this->getUrlModel()->getUrlInStore($this, $params);
    }

    /**
     * Formats URL key
     *
     * @param string $str
     * @param null|string $locale
     * @return string
     */
    public function formatUrlKey($str, $locale = null)
    {
        return $this->getUrlModel()->formatUrlKey($str, $locale);
    }

    /**
     * Retrieve Product Url Path (include category)
     *
     * @param Mage_Catalog_Model_Category $category
     * @return string
     */
    public function getUrlPath($category = null)
    {
        return $this->getUrlModel()->getUrlPath($this, $category);
    }

    /**
     * Save current attribute with code $code and assign new value
     *
     * @param string $code  Attribute code
     * @param mixed  $value New attribute value
     * @param int    $store Store ID
     */
    public function addAttributeUpdate($code, $value, $store)
    {
        $oldValue = $this->getData($code);
        $oldStore = $this->getStoreId();

        $this->setData($code, $value);
        $this->setStoreId($store);
        $this->getResource()->saveAttribute($this, $code);

        $this->setData($code, $oldValue);
        $this->setStoreId($oldStore);
    }

    /**
     * Renders the object to array
     *
     * @param array $arrAttributes Attribute array
     * @return array
     */
    #[\Override]
    public function toArray(array $arrAttributes = [])
    {
        $data = parent::toArray($arrAttributes);
        if ($stock = $this->getStockItem()) {
            $data['stock_item'] = $stock->toArray();
        }
        unset($data['stock_item']['product']);
        return $data;
    }

    /**
     * Same as setData(), but also initiates the stock item (if it is there)
     *
     * @param array $data Array to form the object from
     * @return $this
     */
    public function fromArray($data)
    {
        if (isset($data['stock_item'])) {
            if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
                $stockItem = Mage::getModel('cataloginventory/stock_item')
                    ->setData($data['stock_item'])
                    ->setProduct($this);
                $this->setStockItem($stockItem);
            }
            unset($data['stock_item']);
        }
        $this->setData($data);
        return $this;
    }

    /**
     * Delete product
     *
     * @return $this
     */
    #[\Override]
    public function delete()
    {
        parent::delete();
        Mage::dispatchEvent($this->_eventPrefix . '_delete_after_done', [$this->_eventObject => $this]);
        return $this;
    }

    /**
     * Returns request path
     *
     * @return string
     */
    public function getRequestPath()
    {
        if (!$this->_getData('request_path')) {
            $this->getProductUrl();
        }
        return $this->_getData('request_path');
    }

    /**
     * Custom function for other modules
     * @return int
     */

    public function getGiftMessageAvailable()
    {
        return $this->_getData('gift_message_available');
    }

    /**
     * Returns rating summary
     *
     * @return mixed
     */
    public function getRatingSummary()
    {
        return $this->_getData('rating_summary');
    }

    /**
     * Check is product composite
     *
     * @return bool
     */
    public function isComposite()
    {
        return $this->getTypeInstance(true)->isComposite($this);
    }

    /**
     * Check if product can be configured
     *
     * @return bool
     */
    public function canConfigure()
    {
        $options = $this->getOptions();
        return !empty($options) || $this->getTypeInstance(true)->canConfigure($this);
    }

    /**
     * Retrieve sku through type instance
     *
     * @return string
     */
    public function getSku()
    {
        return $this->getTypeInstance(true)->getSku($this);
    }

    /**
     * Retrieve weight through type instance
     *
     * @return float
     */
    public function getWeight()
    {
        return $this->getTypeInstance(true)->getWeight($this);
    }

    /**
     * Retrieve option instance
     *
     * @return Mage_Catalog_Model_Product_Option
     */
    public function getOptionInstance()
    {
        if (!$this->_optionInstance) {
            $this->_optionInstance = Mage::getSingleton('catalog/product_option');
        }
        return $this->_optionInstance;
    }

    /**
     * Retrieve options collection of product
     *
     * @return Mage_Catalog_Model_Resource_Product_Option_Collection
     */
    public function getProductOptionsCollection()
    {
        return $this->getOptionInstance()
            ->getProductOptionCollection($this);
    }

    /**
     * Add option to array of product options
     *
     * @return $this
     */
    public function addOption(Mage_Catalog_Model_Product_Option $option)
    {
        $this->_options[$option->getId()] = $option;
        return $this;
    }

    /**
     * Get option from options array of product by given option id
     *
     * @param string $optionId
     * @return Mage_Catalog_Model_Product_Option|null
     */
    public function getOptionById($optionId)
    {
        return $this->_options[$optionId] ?? null;
    }

    /**
     * Get all options of product
     *
     * @return Mage_Catalog_Model_Product_Option[]
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Retrieve is a virtual product
     *
     * @return bool
     */
    public function getIsVirtual()
    {
        return $this->getTypeInstance(true)->isVirtual($this);
    }

    /**
     * Add custom option information to product
     *
     * @param   string $code    Option code
     * @param   mixed  $value   Value of the option
     * @param   int    $product Product ID
     * @return  $this
     */
    public function addCustomOption($code, $value, $product = null)
    {
        $product = $product ?: $this;
        $option = Mage::getModel('catalog/product_configuration_item_option')
            ->addData([
                'product_id' => $product->getId(),
                'product'   => $product,
                'code'      => $code,
                'value'     => $value,
            ]);
        $this->_customOptions[$code] = $option;
        return $this;
    }

    /**
     * Sets custom options for the product
     *
     * @param array $options Array of options
     */
    public function setCustomOptions(array $options)
    {
        $this->_customOptions = $options;
    }

    /**
     * Get all custom options of the product
     *
     * @return array
     */
    public function getCustomOptions()
    {
        return $this->_customOptions;
    }

    /**
     * Get product custom option info
     *
     * @param   string $code
     * @return  Mage_Sales_Model_Quote_Item_Option|null
     */
    public function getCustomOption($code)
    {
        return $this->_customOptions[$code] ?? null;
    }

    /**
     * Checks if there custom option for this product
     *
     * @return bool
     */
    public function hasCustomOptions()
    {
        if (count($this->_customOptions)) {
            return true;
        }
        return false;
    }

    /**
     * Check availability display product in category
     *
     * @param   int $categoryId
     * @return  string
     */
    public function canBeShowInCategory($categoryId)
    {
        return $this->_getResource()->canBeShowInCategory($this, $categoryId);
    }

    /**
     * Retrieve category ids where product is available
     *
     * @return array
     */
    public function getAvailableInCategories()
    {
        return $this->_getResource()->getAvailableInCategories($this);
    }

    /**
     * Retrieve default attribute set id
     *
     * @return int
     */
    public function getDefaultAttributeSetId()
    {
        return $this->getResource()->getEntityType()->getDefaultAttributeSetId();
    }

    /**
     * Return Catalog Product Image helper instance
     *
     * @return Mage_Catalog_Helper_Image
     */
    protected function _getImageHelper()
    {
        return Mage::helper('catalog/image');
    }

    /**
     *  Returns system reserved attribute codes
     *
     *  @return array Reserved attribute names
     */
    public function getReservedAttributes()
    {
        // A typed accessor reads the same data key as the attribute, so it must not reserve a code.
        return [
            'attribute_default_value', 'attribute_text', 'attributes', 'available_in_categories', 'billing_address',
            'cache_id_tags', 'cache_id_tags_with_categories', 'cache_tags', 'category', 'category_collection',
            'category_id', 'category_ids', 'collection', 'created_at', 'cross_sell_link_collection',
            'cross_sell_product_collection', 'cross_sell_product_ids', 'cross_sell_products', 'custom_design_date',
            'custom_option', 'custom_options', 'data', 'data_by_key', 'data_by_path', 'data_set_default',
            'data_using_method', 'default_attribute_set_id', 'entity_id', 'event', 'exists_store_value_flag',
            'final_price', 'formated_price', 'formated_tier_price', 'gift_message_available', 'group_price',
            'grouped_link_collection', 'id', 'id_by_sku', 'id_field_name', 'is_salable', 'is_virtual',
            'link_instance', 'links_title', 'locked_attributes', 'media_attributes', 'media_config',
            'media_gallery_images', 'minimal_price', 'msrp', 'name', 'option_by_id', 'option_instance', 'options',
            'orig_data', 'position', 'preconfigured_values', 'price', 'price_attribute_value', 'price_model',
            'price_store_id', 'product_entities_info', 'product_options_collection', 'product_url',
            'related_link_collection', 'related_product_collection', 'related_product_ids', 'related_products',
            'reserved_attributes', 'resource', 'resource_collection', 'resource_name', 'review_summary',
            'shipping_address', 'sku', 'special_from_date', 'special_price', 'special_to_date', 'status',
            'stock_item', 'store', 'store_id', 'store_ids', 'tier_price', 'tier_price_count', 'type_instance',
            'up_sell_link_collection', 'up_sell_product_collection', 'up_sell_product_ids', 'up_sell_products',
            'url_in_store', 'url_model', 'url_path', 'visible_in_catalog_statuses', 'visible_in_site_visibilities',
            'visible_statuses', 'website_ids', 'website_price_rate', 'website_store_ids', 'weight',
        ];
    }

    /**
     *  Check whether attribute reserved or not
     *
     *  @param Mage_Catalog_Model_Entity_Attribute $attribute Attribute model object
     *  @return bool
     */
    public function isReservedAttribute($attribute)
    {
        return $attribute->getIsUserDefined()
            && in_array($attribute->getAttributeCode(), $this->getReservedAttributes());
    }

    /**
     * Reset all model data
     *
     * @return $this
     */
    public function reset()
    {
        $this->unlockAttributes();
        $this->_clearData();
        return $this;
    }

    /**
     * Get cache tags associated with object id
     *
     * @return array
     */
    public function getCacheIdTagsWithCategories()
    {
        $tags = $this->getCacheTags();
        $affectedCategoryIds = $this->_getResource()->getCategoryIdsWithAnchors($this);
        foreach ($affectedCategoryIds as $categoryId) {
            $tags[] = Mage_Catalog_Model_Category::CACHE_TAG . '_' . $categoryId;
        }
        return $tags;
    }

    /**
     * Remove model object related cache
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    public function cleanModelCache()
    {
        $tags = $this->getCacheIdTagsWithCategories();
        if ($tags !== false) {
            Mage::app()->cleanCache($tags);
        }
        return $this;
    }

    /**
     * Check for empty SKU on each product
     *
     * @return bool|null
     */
    public function isProductsHasSku(array $productIds)
    {
        $products = $this->_getResource()->getProductsSku($productIds);
        if (count($products)) {
            return array_all($products, fn($product) => $product['sku'] !== '');
        }
        return null;
    }

    /**
     * Parse buyRequest into options values used by product
     *
     * @return \Maho\DataObject
     */
    public function processBuyRequest(\Maho\DataObject $buyRequest)
    {
        $options = new \Maho\DataObject();

        /* add product custom options data */
        $customOptions = $buyRequest->getOptions();
        if (is_array($customOptions)) {
            foreach ($customOptions as $key => $value) {
                if ($value === '') {
                    unset($customOptions[$key]);
                }
            }
            $options->setOptions($customOptions);
        }

        /* add product type selected options data */
        $type = $this->getTypeInstance(true);
        $typeSpecificOptions = $type->processBuyRequest($this, $buyRequest);
        $options->addData($typeSpecificOptions);

        /* check correctness of product's options */
        $options->setErrors($type->checkProductConfiguration($this, $buyRequest));

        return $options;
    }

    /**
     * Get preconfigured values from product
     *
     * @return \Maho\DataObject
     */
    public function getPreconfiguredValues()
    {
        $preconfiguredValues = $this->getData('preconfigured_values');
        if (!$preconfiguredValues) {
            $preconfiguredValues = new \Maho\DataObject();
        }

        return $preconfiguredValues;
    }

    /**
     * Prepare product custom options.
     * To be sure that all product custom options does not has ID and has product instance
     *
     * @return $this
     */
    public function prepareCustomOptions()
    {
        foreach ($this->getCustomOptions() as $option) {
            if (!is_object($option->getProduct()) || $option->getId()) {
                $this->addCustomOption($option->getCode(), $option->getValue());
            }
        }

        return $this;
    }

    /**
     * Clearing references on product
     *
     * @return $this
     */
    #[\Override]
    protected function _clearReferences()
    {
        $this->_clearOptionReferences();
        return $this;
    }

    /**
     * Clearing product's data
     *
     * @return $this
     */
    #[\Override]
    protected function _clearData()
    {
        foreach ($this->_data as $data) {
            if (is_object($data) && method_exists($data, 'reset')) {
                $data->reset();
            }
        }

        $this->setData([]);
        $this->setOrigData();
        $this->_customOptions         = [];
        $this->_optionInstance        = null;
        $this->_options               = [];
        $this->_canAffectOptions      = false;
        $this->_errors                = [];
        $this->_defaultValues         = [];
        $this->_storeValuesFlags      = [];
        $this->_lockedAttributes      = [];
        $this->_typeInstance          = null;
        $this->_typeInstanceSingleton = null;
        $this->_linkInstance          = null;
        $this->_isDuplicable          = true;
        $this->_calculatePrice        = true;
        $this->_stockItem             = null;
        $this->_isDeleteable          = true;
        $this->_isReadonly            = false;

        return $this;
    }

    /**
     * Clearing references to product from product's options
     *
     * @return $this
     */
    protected function _clearOptionReferences()
    {
        /**
         * unload product options
         */
        if (!empty($this->_options)) {
            foreach ($this->_options as $key => $option) {
                $option->setProduct();
                $option->clearInstance();
            }
        }

        return $this;
    }

    /**
     * Retrieve product entities info as array
     *
     * @param string|array $columns One or several columns
     * @return array
     */
    public function getProductEntitiesInfo($columns = null)
    {
        return $this->_getResource()->getProductEntitiesInfo($columns);
    }

    /**
     * Checks whether product has disabled status
     *
     * @return bool
     */
    public function isDisabled()
    {
        return $this->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
    }

    /**
     * Callback function which called after transaction commit in resource model
     *
     * @return $this
     */
    #[\Override]
    public function afterCommitCallback()
    {
        parent::afterCommitCallback();

        /** @var \Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getSingleton('index/indexer');
        $indexer->processEntityAction($this, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);

        return $this;
    }

    /**
     * Checks event attribute for initialization as an event object
     *
     * @return bool
     */
    public function getEvent()
    {
        $event = parent::getEvent();
        if (is_string($event)) {
            $event = false;
        }

        return $event;
    }

    /**
     * @param int $storeId
     * @return Mage_Review_Model_Review_Summary
     */
    public function getReviewSummary($storeId = null)
    {
        $storeId ??= Mage::app()->getStore()->getId();
        if (empty($this->_reviewSummary[$storeId])) {
            $this->_reviewSummary[$storeId] = Mage::getModel('review/review_summary')
                ->setStoreId($storeId)
                ->load($this->getId());
        }
        return $this->_reviewSummary[$storeId];
    }

    public function setAddToCartUrl(?string $value): static
    {
        return $this->setData('add_to_cart_url', $value);
    }

    public function getAllowedInRss(): ?bool
    {
        $value = $this->getData('allowed_in_rss');
        return $value === null ? null : (bool) $value;
    }

    public function setAllowedInRss(?bool $value = true): static
    {
        return $this->setData('allowed_in_rss', $value);
    }

    public function getAllowedPriceInRss(): ?bool
    {
        $value = $this->getData('allowed_price_in_rss');
        return $value === null ? null : (bool) $value;
    }

    public function setAllowedPriceInRss(?bool $value = true): static
    {
        return $this->setData('allowed_price_in_rss', $value);
    }

    public function setAffectedCategoryIds(?array $value): static
    {
        return $this->setData('affected_category_ids', $value);
    }

    public function getAppliedRates(): ?array
    {
        return $this->getData('applied_rates');
    }

    public function setAppliedRates(?array $value): static
    {
        return $this->setData('applied_rates', $value);
    }

    public function getAttributesConfigurationReadonly(): ?bool
    {
        $value = $this->getData('attributes_configuration_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getAttributeSetId(): ?int
    {
        $value = $this->getData('attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeSetId(?int $value): static
    {
        return $this->setData('attribute_set_id', $value);
    }

    public function getBaseRowTotal(): ?float
    {
        $value = $this->getData('base_row_total');
        return $value === null ? null : (float) $value;
    }

    public function getBundleOptionsData(): ?array
    {
        return $this->getData('bundle_options_data');
    }

    public function setBundleOptionsData(?array $value): static
    {
        return $this->setData('bundle_options_data', $value);
    }

    public function getBundleSelectionsData(): ?array
    {
        return $this->getData('bundle_selections_data');
    }

    public function setBundleSelectionsData(?array $value): static
    {
        return $this->setData('bundle_selections_data', $value);
    }

    public function getCanSaveBundleSelections(): ?bool
    {
        $value = $this->getData('can_save_bundle_selections');
        return $value === null ? null : (bool) $value;
    }

    public function setCanSaveBundleSelections(?bool $value = true): static
    {
        return $this->setData('can_save_bundle_selections', $value);
    }

    public function getCanSaveCustomOptions(): ?bool
    {
        $value = $this->getData('can_save_custom_options');
        return $value === null ? null : (bool) $value;
    }

    public function setCanSaveCustomOptions(?bool $value = true): static
    {
        return $this->setData('can_save_custom_options', $value);
    }

    public function getCanSaveConfigurableAttributes(): ?bool
    {
        $value = $this->getData('can_save_configurable_attributes');
        return $value === null ? null : (bool) $value;
    }

    public function getCanShowPrice(): ?bool
    {
        $value = $this->getData('can_show_price');
        return $value === null ? null : (bool) $value;
    }

    public function getCategoriesReadonly(): ?bool
    {
        $value = $this->getData('categories_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function setCartQty(?float $value): static
    {
        return $this->setData('cart_qty', $value);
    }

    public function setCategory(?Mage_Catalog_Model_Category $value): static
    {
        return $this->setData('category', $value);
    }

    public function getChildAttributeLabelMapping(): ?array
    {
        return $this->getData('child_attribute_label_mapping');
    }

    public function getChildrenProducts(): ?array
    {
        return $this->getData('children_products');
    }

    public function setChildrenProducts(?array $value): static
    {
        return $this->setData('children_products', $value);
    }

    public function getCompositeReadonly(): ?bool
    {
        $value = $this->getData('composite_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getConfigurableAttributesData(): ?array
    {
        return $this->getData('configurable_attributes_data');
    }

    public function getConfigurableImagesFallbackArray(): ?array
    {
        return $this->getData('configurable_images_fallback_array');
    }

    public function setConfigurableImagesFallbackArray(?array $value): static
    {
        return $this->setData('configurable_images_fallback_array', $value);
    }

    public function getConfigurablePrice(): ?float
    {
        $value = $this->getData('configurable_price');
        return $value === null ? null : (float) $value;
    }

    public function setConfigurablePrice(?float $value): static
    {
        return $this->setData('configurable_price', $value);
    }

    public function getConfigurableProductsData(): ?array
    {
        return $this->getData('configurable_products_data');
    }

    public function getConfigureMode(): ?bool
    {
        $value = $this->getData('configure_mode');
        return $value === null ? null : (bool) $value;
    }

    public function setConfigureMode(?bool $value = true): static
    {
        return $this->setData('configure_mode', $value);
    }

    public function getCost(): ?float
    {
        $value = $this->getData('cost');
        return $value === null ? null : (float) $value;
    }

    public function getCustomLayoutUpdate(): ?string
    {
        $value = $this->getData('custom_layout_update');
        return $value === null ? null : (string) $value;
    }

    public function getCustomerGroupId(): ?int
    {
        $value = $this->getData('customer_group_id');
        return $value === null ? null : (int) $value;
    }

    public function getCrossSellLinkData(): ?array
    {
        return $this->getData('cross_sell_link_data');
    }

    public function setCrossSellLinkData(?array $value): static
    {
        return $this->setData('cross_sell_link_data', $value);
    }

    public function setCrossSellProducts(?array $value): static
    {
        return $this->setData('cross_sell_products', $value);
    }

    public function setCrossSellProductIds(?array $value): static
    {
        return $this->setData('cross_sell_product_ids', $value);
    }

    public function setCustomerGroupId(?int $value): static
    {
        return $this->setData('customer_group_id', $value);
    }

    public function getEntityTypeId(): ?int
    {
        $value = $this->getData('entity_type_id');
        return $value === null ? null : (int) $value;
    }

    public function setExcludeUrlRewrite(?bool $value = true): static
    {
        return $this->setData('exclude_url_rewrite', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function getDisableAddToCart(): ?bool
    {
        $value = $this->getData('disable_add_to_cart');
        return $value === null ? null : (bool) $value;
    }

    public function setDisableAddToCart(?bool $value = true): static
    {
        return $this->setData('disable_add_to_cart', $value);
    }

    public function getDownloadableData(): ?array
    {
        return $this->getData('downloadable_data');
    }

    public function setDownloadableData(?array $value): static
    {
        return $this->setData('downloadable_data', $value);
    }

    public function getDownloadableLinks(): ?array
    {
        return $this->getData('downloadable_links');
    }

    public function setDownloadableLinks(?array $value): static
    {
        return $this->setData('downloadable_links', $value);
    }

    public function getDownloadableReadonly(): ?bool
    {
        $value = $this->getData('downloadable_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getDownloadableSamples(): ?Mage_Downloadable_Model_Resource_Sample_Collection
    {
        return $this->getData('downloadable_samples');
    }

    public function setDownloadableSamples(?Mage_Downloadable_Model_Resource_Sample_Collection $value): static
    {
        return $this->setData('downloadable_samples', $value);
    }

    public function getForceReindexRequired(): ?bool
    {
        $value = $this->getData('force_reindex_required');
        return $value === null ? null : (bool) $value;
    }

    public function getGroupedLinkData(): ?array
    {
        return $this->getData('grouped_link_data');
    }

    public function setGroupedLinkData(?array $value): static
    {
        return $this->setData('grouped_link_data', $value);
    }

    public function setHasError(?bool $value = true): static
    {
        return $this->setData('has_error', $value);
    }

    public function getHasError(): ?bool
    {
        $value = $this->getData('has_error');
        return $value === null ? null : (bool) $value;
    }

    public function getHasOptions(): ?bool
    {
        $value = $this->getData('has_options');
        return $value === null ? null : (bool) $value;
    }

    public function setHasOptions(?bool $value = true): static
    {
        return $this->setData('has_options', $value);
    }

    public function getImage(): ?string
    {
        $value = $this->getData('image');
        return $value === null ? null : (string) $value;
    }

    public function getInventoryReadonly(): ?bool
    {
        $value = $this->getData('inventory_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getIsChangedCategories(): ?bool
    {
        $value = $this->getData('is_changed_categories');
        return $value === null ? null : (bool) $value;
    }

    public function setIsChangedCategories(?bool $value = true): static
    {
        return $this->setData('is_changed_categories', $value);
    }

    public function getIsChangedWebsites(): ?bool
    {
        $value = $this->getData('is_changed_websites');
        return $value === null ? null : (bool) $value;
    }

    public function setIsChangedWebsites(?bool $value = true): static
    {
        return $this->setData('is_changed_websites', $value);
    }

    public function getIsCustomOptionChanged(): ?bool
    {
        $value = $this->getData('is_custom_option_changed');
        return $value === null ? null : (bool) $value;
    }

    public function setIsCustomOptionChanged(?bool $value = true): static
    {
        return $this->setData('is_custom_option_changed', $value);
    }

    public function getIsDefault(): ?bool
    {
        $value = $this->getData('is_default');
        return $value === null ? null : (bool) $value;
    }

    public function getIsRelationsChanged(): ?bool
    {
        $value = $this->getData('is_relations_changed');
        return $value === null ? null : (bool) $value;
    }

    public function setIsRelationsChanged(?bool $value = true): static
    {
        return $this->setData('is_relations_changed', $value);
    }

    public function getIsDuplicate(): ?bool
    {
        $value = $this->getData('is_duplicate');
        return $value === null ? null : (bool) $value;
    }

    public function setIsDuplicate(?bool $value = true): static
    {
        return $this->setData('is_duplicate', $value);
    }

    public function setIsQtyDecimal(?bool $value = true): static
    {
        return $this->setData('is_qty_decimal', $value);
    }

    public function setIsInStock(?bool $value = true): static
    {
        return $this->setData('is_in_stock', $value);
    }

    public function getIsMassupdate(): ?bool
    {
        $value = $this->getData('is_massupdate');
        return $value === null ? null : (bool) $value;
    }

    public function setIsMassupdate(?bool $value = true): static
    {
        return $this->setData('is_massupdate', $value);
    }

    public function getIsRecurring(): ?int
    {
        $value = $this->getData('is_recurring');
        return $value === null ? null : (int) $value;
    }

    public function setIsSalable(?bool $value = true): static
    {
        return $this->setData('is_salable', $value);
    }

    public function setIsSuperMode(?bool $value = true): static
    {
        return $this->setData('is_super_mode', $value);
    }

    public function setLinksExist(?bool $value = true): static
    {
        return $this->setData('links_exist', $value);
    }

    public function getLinksPurchasedSeparately(): ?int
    {
        $value = $this->getData('links_purchased_separately');
        return $value === null ? null : (int) $value;
    }

    /**
     * An int, not a bool: in catalog a false value is the "use default scope value"
     * sentinel, so a required attribute set to false is read as missing.
     */
    public function setLinksPurchasedSeparately(?int $value): static
    {
        return $this->setData('links_purchased_separately', $value);
    }

    public function getListSwatchAttrValues(): ?array
    {
        return $this->getData('list_swatch_attr_values');
    }

    public function getMatchedRules(): ?array
    {
        return $this->getData('matched_rules');
    }

    public function setMediaAttributes(?array $value): static
    {
        return $this->setData('media_attributes', $value);
    }

    public function getMediaGallery(): ?array
    {
        return $this->getData('media_gallery');
    }

    public function setMediaGallery(array|false|null $value): static
    {
        return $this->setData('media_gallery', $value);
    }

    public function getMessage(): ?string
    {
        $value = $this->getData('message');
        return $value === null ? null : (string) $value;
    }

    public function getMetaDescription(): ?string
    {
        $value = $this->getData('meta_description');
        return $value === null ? null : (string) $value;
    }

    public function getMetaKeyword(): ?string
    {
        $value = $this->getData('meta_keyword');
        return $value === null ? null : (string) $value;
    }

    public function getMetaTitle(): ?string
    {
        $value = $this->getData('meta_title');
        return $value === null ? null : (string) $value;
    }

    public function getMsrpEnabled(): ?int
    {
        $value = $this->getData('msrp_enabled');
        return $value === null ? null : (int) $value;
    }

    public function getMsrpDisplayActualPriceType(): ?string
    {
        $value = $this->getData('msrp_display_actual_price_type');
        return $value === null ? null : (string) $value;
    }

    public function setNeedStoreForReindex(?bool $value = true): static
    {
        return $this->setData('need_store_for_reindex', $value);
    }

    public function getOption(): ?Mage_Bundle_Model_Option
    {
        return $this->getData('option');
    }

    public function setOption(?Mage_Bundle_Model_Option $value): static
    {
        return $this->setData('option', $value);
    }

    public function getOptionId(): ?int
    {
        $value = $this->getData('option_id');
        return $value === null ? null : (int) $value;
    }

    public function getOptionsReadonly(): ?bool
    {
        $value = $this->getData('options_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function setOptionsValidationFail(?bool $value = true): static
    {
        return $this->setData('options_validation_fail', $value);
    }

    public function getOriginalId(): ?int
    {
        $value = $this->getData('original_id');
        return $value === null ? null : (int) $value;
    }

    public function setOriginalId(?int $value): static
    {
        return $this->setData('original_id', $value);
    }

    public function getPageLayout(): ?string
    {
        $value = $this->getData('page_layout');
        return $value === null ? null : (string) $value;
    }

    /**
     * A product has no parent_id column. The configurable price path stores a flag
     * here, and Mage_Eav_Model_Entity_Abstract::save() stores an int. Every reader
     * only tests the value for truth.
     */
    public function getParentId(): bool|int|null
    {
        return $this->getData('parent_id');
    }

    public function setParentId(bool|int|null $value): static
    {
        return $this->setData('parent_id', $value);
    }

    public function getParentProductId(): ?int
    {
        $value = $this->getData('parent_product_id');
        return $value === null ? null : (int) $value;
    }

    public function getParentProductIds(): ?array
    {
        return $this->getData('parent_product_ids');
    }

    public function setParentProductIds(?array $value): static
    {
        return $this->setData('parent_product_ids', $value);
    }

    public function getPopularity(): ?int
    {
        $value = $this->getData('popularity');
        return $value === null ? null : (int) $value;
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPrice(float|false|null $value): static
    {
        return $this->setData('price', $value);
    }

    public function getPriceType(): ?int
    {
        $value = $this->getData('price_type');
        return $value === null ? null : (int) $value;
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function getProductOptions(): ?array
    {
        return $this->getData('product_options');
    }

    public function setProductOptions(?array $value): static
    {
        return $this->setData('product_options', $value);
    }

    public function setProductTags(?Mage_Tag_Model_Resource_Tag_Collection $value): static
    {
        return $this->setData('product_tags', $value);
    }

    public function setProductUrl(?string $value): static
    {
        return $this->setData('product_url', $value);
    }

    public function setQuoteItemPrice(?float $value): static
    {
        return $this->setData('quote_item_price', $value);
    }

    public function setQuoteItemRowTotal(?float $value): static
    {
        return $this->setData('quote_item_row_total', $value);
    }

    public function setQuoteItemQty(?int $value): static
    {
        return $this->setData('quote_item_qty', $value);
    }

    public function setQuoteQty(?float $value): static
    {
        return $this->setData('quote_qty', $value);
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
    }

    public function setQty(?float $value): static
    {
        return $this->setData('qty', $value);
    }

    public function setRatingSummary(?\Maho\DataObject $value): static
    {
        return $this->setData('rating_summary', $value);
    }

    public function setRatingVotes(?Mage_Rating_Model_Resource_Rating_Option_Vote_Collection $value): static
    {
        return $this->setData('rating_votes', $value);
    }

    public function getRealPriceHtml(): ?string
    {
        $value = $this->getData('real_price_html');
        return $value === null ? null : (string) $value;
    }

    public function setRealPriceHtml(?string $value): static
    {
        return $this->setData('real_price_html', $value);
    }

    public function getRelatedReadonly(): ?bool
    {
        $value = $this->getData('related_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function setRelatedLinkData(?array $value): static
    {
        return $this->setData('related_link_data', $value);
    }

    public function getRecurringProfile(): ?array
    {
        return $this->getData('recurring_profile');
    }

    public function getRelatedLinkData(): ?array
    {
        return $this->getData('related_link_data');
    }

    public function setRelatedProducts(?array $value): static
    {
        return $this->setData('related_products', $value);
    }

    public function setRelatedProductIds(?array $value): static
    {
        return $this->setData('related_product_ids', $value);
    }

    public function getRequiredOptions(): ?bool
    {
        $value = $this->getData('required_options');
        return $value === null ? null : (bool) $value;
    }

    public function setRequiredOptions(?bool $value = true): static
    {
        return $this->setData('required_options', $value);
    }

    public function getReviewId(): ?int
    {
        $value = $this->getData('review_id');
        return $value === null ? null : (int) $value;
    }

    public function getSamplesTitle(): ?string
    {
        $value = $this->getData('samples_title');
        return $value === null ? null : (string) $value;
    }

    public function getSelectionCanChangeQty(): ?bool
    {
        $value = $this->getData('selection_can_change_qty');
        return $value === null ? null : (bool) $value;
    }

    public function getSelectionId(): ?int
    {
        $value = $this->getData('selection_id');
        return $value === null ? null : (int) $value;
    }

    public function getSelectionPriceType(): ?int
    {
        $value = $this->getData('selection_price_type');
        return $value === null ? null : (int) $value;
    }

    public function getSelectionPriceValue(): ?float
    {
        $value = $this->getData('selection_price_value');
        return $value === null ? null : (float) $value;
    }

    public function getSelectionQty(): ?float
    {
        $value = $this->getData('selection_qty');
        return $value === null ? null : (float) $value;
    }

    public function getShipmentType(): ?int
    {
        $value = $this->getData('shipment_type');
        return $value === null ? null : (int) $value;
    }

    public function getShortDescription(): ?string
    {
        $value = $this->getData('short_description');
        return $value === null ? null : (string) $value;
    }

    public function setShortDescription(string|false|null $value): static
    {
        return $this->setData('short_description', $value);
    }

    public function getSkipCheckRequiredOption(): ?bool
    {
        $value = $this->getData('skip_check_required_option');
        return $value === null ? null : (bool) $value;
    }

    public function setSkipCheckRequiredOption(?bool $value = true): static
    {
        return $this->setData('skip_check_required_option', $value);
    }

    public function setSku(?string $value): static
    {
        return $this->setData('sku', $value);
    }

    public function getSmallImage(): ?string
    {
        $value = $this->getData('small_image');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(int|false|null $value): static
    {
        return $this->setData('status', $value);
    }

    public function getStickWithinParent(): ?Mage_Sales_Model_Quote_Item
    {
        return $this->getData('stick_within_parent');
    }

    public function getStockData(): ?array
    {
        return $this->getData('stock_data');
    }

    public function setStockData(?array $value): static
    {
        return $this->setData('stock_data', $value);
    }

    public function setStore(Mage_Core_Model_Store|int|null $value): static
    {
        return $this->setData('store', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function setStoreIds(?array $value): static
    {
        return $this->setData('store_ids', $value);
    }

    public function getSwatchPrices(): ?array
    {
        return $this->getData('swatch_prices');
    }

    public function getTaxClassId(): ?int
    {
        $value = $this->getData('tax_class_id');
        return $value === null ? null : (int) $value;
    }

    public function getThumbnail(): ?string
    {
        $value = $this->getData('thumbnail');
        return $value === null ? null : (string) $value;
    }

    public function getTaxPercent(): ?float
    {
        $value = $this->getData('tax_percent');
        return $value === null ? null : (float) $value;
    }

    public function setTaxPercent(?float $value): static
    {
        return $this->setData('tax_percent', $value);
    }

    public function setTypeId(?string $value): static
    {
        return $this->setData('type_id', $value);
    }

    public function getTypeHasOptions(): ?bool
    {
        $value = $this->getData('type_has_options');
        return $value === null ? null : (bool) $value;
    }

    public function setTypeHasOptions(?bool $value = true): static
    {
        return $this->setData('type_has_options', $value);
    }

    public function getTypeHasRequiredOptions(): ?bool
    {
        $value = $this->getData('type_has_required_options');
        return $value === null ? null : (bool) $value;
    }

    public function setTypeHasRequiredOptions(?bool $value = true): static
    {
        return $this->setData('type_has_required_options', $value);
    }

    public function getUpsellReadonly(): ?bool
    {
        $value = $this->getData('upsell_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getUpSellLinkData(): ?array
    {
        return $this->getData('up_sell_link_data');
    }

    public function setUpSellLinkData(?array $value): static
    {
        return $this->setData('up_sell_link_data', $value);
    }

    public function setUpSellProducts(?array $value): static
    {
        return $this->setData('up_sell_products', $value);
    }

    public function setUpSellProductIds(?array $value): static
    {
        return $this->setData('up_sell_product_ids', $value);
    }

    public function getUrlDataObject(): ?\Maho\DataObject
    {
        return $this->getData('url_data_object');
    }

    public function setUrlDataObject(?\Maho\DataObject $value): static
    {
        return $this->setData('url_data_object', $value);
    }

    public function getUrlKey(): ?string
    {
        $value = $this->getData('url_key');
        return $value === null ? null : (string) $value;
    }

    public function setUrlKey(string|false|null $value): static
    {
        return $this->setData('url_key', $value);
    }

    public function setUrlPath(string|false|null $value): static
    {
        return $this->setData('url_path', $value);
    }

    public function getVisibility(): ?int
    {
        $value = $this->getData('visibility');
        return $value === null ? null : (int) $value;
    }

    public function setVisibility(int|false|null $value): static
    {
        return $this->setData('visibility', $value);
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

    public function setWebsiteIds(?array $value): static
    {
        return $this->setData('website_ids', $value);
    }

    public function getWebsitesReadonly(): ?bool
    {
        $value = $this->getData('websites_readonly');
        return $value === null ? null : (bool) $value;
    }

    public function getWeightType(): ?int
    {
        $value = $this->getData('weight_type');
        return $value === null ? null : (int) $value;
    }

    public function getWishlistItemId(): ?int
    {
        $value = $this->getData('wishlist_item_id');
        return $value === null ? null : (int) $value;
    }

    public function getWishlistStoreId(): ?int
    {
        $value = $this->getData('wishlist_store_id');
        return $value === null ? null : (int) $value;
    }

    public function setWishlistStoreId(?int $value): static
    {
        return $this->setData('wishlist_store_id', $value);
    }
}
