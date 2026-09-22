<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

/**
 * Catalog Inventory Stock Model
 *
 * @package    Mage_CatalogInventory
 *
 * @method Mage_CatalogInventory_Model_Resource_Stock_Item _getResource()
 * @method Mage_CatalogInventory_Model_Resource_Stock_Item getResource()
 * @method Mage_CatalogInventory_Model_Resource_Stock_Item_Collection getCollection()
 *
 * @method bool hasStockStatusChangedAutomaticallyFlag()
 * @method $this hasIsChildItem()
 * @method $this unsIsChildItem()
 * @method bool hasStockQty()
 * @method float getQtyCorrection()
 */
class Mage_CatalogInventory_Model_Stock_Item extends Mage_Core_Model_Abstract
{
    public const XML_PATH_GLOBAL                = 'cataloginventory/options/';
    public const XML_PATH_CAN_SUBTRACT          = 'cataloginventory/options/can_subtract';
    public const XML_PATH_CAN_BACK_IN_STOCK     = 'cataloginventory/options/can_back_in_stock';
    public const XML_PATH_SYNC_AVAIL_WITH_QTY   = 'cataloginventory/options/sync_stock_availability_with_qty';

    public const XML_PATH_ITEM                  = 'cataloginventory/item_options/';
    public const XML_PATH_MIN_QTY               = 'cataloginventory/item_options/min_qty';
    public const XML_PATH_MIN_SALE_QTY          = 'cataloginventory/item_options/min_sale_qty';
    public const XML_PATH_MAX_SALE_QTY          = 'cataloginventory/item_options/max_sale_qty';
    public const XML_PATH_BACKORDERS            = 'cataloginventory/item_options/backorders';
    public const XML_PATH_NOTIFY_STOCK_QTY      = 'cataloginventory/item_options/notify_stock_qty';
    public const XML_PATH_MANAGE_STOCK          = 'cataloginventory/item_options/manage_stock';
    public const XML_PATH_ENABLE_QTY_INCREMENTS = 'cataloginventory/item_options/enable_qty_increments';
    public const XML_PATH_QTY_INCREMENTS        = 'cataloginventory/item_options/qty_increments';

    public const ENTITY                         = 'cataloginventory_stock_item';

    /**
     * @var array
     */
    private $_minSaleQtyCache = [];

    /**
     * @var float|false
     */
    protected $_qtyIncrements;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'cataloginventory_stock_item';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getItem() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'item';

    /**
     * Associated product instance
     *
     * @var Mage_Catalog_Model_Product|null
     */
    protected $_productInstance = null;

    /**
     * Customer group id
     *
     * @var int|null
     */
    protected $_customerGroupId;

    /**
     * Whether index events should be processed immediately
     *
     * @var bool
     */
    protected $_processIndexEvents = true;

    #[\Override]
    protected function _construct()
    {
        $this->_init('cataloginventory/stock_item');
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return void
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        // pre 1.6 fields names, old => new
        $this->_oldFieldsMap = [
            'stock_status_changed_automatically' => 'stock_status_changed_auto',
            'use_config_enable_qty_increments'   => 'use_config_enable_qty_inc',
        ];
    }

    /**
     * Retrieve stock identifier
     *
     * @todo multi stock
     * @return int
     */
    public function getStockId()
    {
        return 1;
    }

    /**
     * Retrieve Product Id data wrapper
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->_getData('product_id');
    }

    /**
     * Load item data by product
     *
     * @param   mixed $product
     * @return  $this
     */
    public function loadByProduct($product)
    {
        if ($product instanceof Mage_Catalog_Model_Product) {
            $product = $product->getId();
        }
        $this->_getResource()->loadByProductId($this, $product);
        $this->setOrigData();
        return $this;
    }

    /**
     * Subtract quote item quantity
     *
     * @param   float $qty
     * @return  $this
     */
    public function subtractQty($qty)
    {
        if ($this->canSubtractQty()) {
            $this->setQty($this->getQty() - $qty);
        }
        return $this;
    }

    /**
     * Check if is possible subtract value from item qty
     *
     * @return bool
     */
    public function canSubtractQty()
    {
        return $this->getManageStock() && Mage::getStoreConfigFlag(self::XML_PATH_CAN_SUBTRACT);
    }

    /**
     * Add quantity process
     *
     * @param float $qty
     * @return $this
     */
    public function addQty($qty)
    {
        if (!$this->getManageStock()) {
            return $this;
        }
        $config = Mage::getStoreConfigFlag(self::XML_PATH_CAN_SUBTRACT);
        if (!$config) {
            return $this;
        }

        $this->setQty($this->getQty() + $qty);
        return $this;
    }

    /**
     * Retrieve Store Id (product or current)
     *
     * @return int
     */
    public function getStoreId()
    {
        $storeId = $this->getData('store_id');
        if (is_null($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
            $this->setData('store_id', $storeId);
        }
        return $storeId;
    }

    /**
     * Adding stock data to product
     *
     * @return  $this
     */
    public function assignProduct(Mage_Catalog_Model_Product $product)
    {
        if (!$this->getId() || !$this->getProductId()) {
            $this->_getResource()->loadByProductId($this, $product->getId());
            $this->setOrigData();
        }

        $this->setProduct($product);
        $product->setStockItem($this);

        $product->setIsInStock($this->getIsInStock());
        Mage::getSingleton('cataloginventory/stock_status')
            ->assignProduct($product, $this->getStockId(), $this->getStockStatus());
        return $this;
    }

    /**
     * Retrieve minimal quantity available for item status in stock
     *
     * @return float
     */
    public function getMinQty()
    {
        return (float) ($this->getUseConfigMinQty() ? Mage::getStoreConfig(self::XML_PATH_MIN_QTY)
            : $this->getData('min_qty'));
    }

    /**
     * Getter for customer group id
     *
     * @return int
     */
    public function getCustomerGroupId()
    {
        return $this->_customerGroupId;
    }

    /**
     * Setter for customer group id
     *
     * @param int $value Value of customer group id
     * @return $this
     */
    public function setCustomerGroupId($value)
    {
        $this->_customerGroupId = $value;
        return $this;
    }

    /**
     * Retrieve Minimum Qty Allowed in Shopping Cart or NULL when there is no limitation
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getMinSaleQty(): ?float
    {
        $customerGroupId = $this->getCustomerGroupId();
        if (!$customerGroupId) {
            $customerGroupId = Mage::app()->getStore()->isAdmin()
                ? Mage_Customer_Model_Group::CUST_GROUP_ALL
                : Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        if (!isset($this->_minSaleQtyCache[$customerGroupId])) {
            $minSaleQty = $this->getUseConfigMinSaleQty()
                ? Mage::helper('cataloginventory/minsaleqty')->getConfigValue($customerGroupId)
                : $this->getData('min_sale_qty');

            $this->_minSaleQtyCache[$customerGroupId] = empty($minSaleQty) ? 0 : (float) $minSaleQty;
        }

        return $this->_minSaleQtyCache[$customerGroupId] ?: null;
    }

    /**
     * Retrieve Maximum Qty Allowed in Shopping Cart data wrapper
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getMaxSaleQty(): float
    {
        return (float) ($this->getUseConfigMaxSaleQty() ? Mage::getStoreConfig(self::XML_PATH_MAX_SALE_QTY)
            : $this->getData('max_sale_qty'));
    }

    /**
     * Retrieve Notify for Quantity Below data wrapper
     *
     * @return float
     */
    public function getNotifyStockQty()
    {
        if ($this->getUseConfigNotifyStockQty()) {
            return Mage::getStoreConfigAsFloat(self::XML_PATH_NOTIFY_STOCK_QTY);
        }
        return (float) $this->getData('notify_stock_qty');
    }

    /**
     * Retrieve whether Quantity Increments is enabled
     *
     * @return bool
     */
    public function getEnableQtyIncrements()
    {
        return $this->getUseConfigEnableQtyIncrements()
            ? Mage::getStoreConfigFlag(self::XML_PATH_ENABLE_QTY_INCREMENTS)
            : (bool) $this->getData('enable_qty_increments');
    }

    /**
     * Retrieve Quantity Increments data wrapper
     *
     * @return float|false
     */
    public function getQtyIncrements()
    {
        if ($this->_qtyIncrements === null) {
            if ($this->getEnableQtyIncrements()) {
                $this->_qtyIncrements = (float) ($this->getUseConfigQtyIncrements()
                    ? Mage::getStoreConfig(self::XML_PATH_QTY_INCREMENTS)
                    : $this->getData('qty_increments'));
                if ($this->_qtyIncrements <= 0) {
                    $this->_qtyIncrements = false;
                }
            } else {
                $this->_qtyIncrements = false;
            }
        }
        return $this->_qtyIncrements;
    }

    /**
     * Retrieve backorders status
     *
     * @return int
     */
    public function getBackorders()
    {
        if ($this->getUseConfigBackorders()) {
            return Mage::getStoreConfigAsInt(self::XML_PATH_BACKORDERS);
        }
        return $this->getData('backorders');
    }

    /**
     * Retrieve Manage Stock data wrapper
     *
     * @return int
     */
    public function getManageStock()
    {
        if ($this->getUseConfigManageStock()) {
            return (int) Mage::getStoreConfigFlag(self::XML_PATH_MANAGE_STOCK);
        }
        return $this->getData('manage_stock');
    }

    /**
     * Retrieve can Back in stock
     *
     * @return bool
     */
    public function getCanBackInStock()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CAN_BACK_IN_STOCK);
    }

    /**
     * Check quantity
     *
     * @param   float $qty
     * @throws  Mage_Core_Exception
     * @return  bool
     */
    public function checkQty($qty)
    {
        if (!$this->getManageStock() || Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        if ($this->getQty() - $this->getMinQty() - $qty < 0) {
            switch ($this->getBackorders()) {
                case Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY:
                case Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY:
                    break;
                default:
                    return false;
            }
        }
        return true;
    }

    /**
     * Returns suggested qty that satisfies qty increments and minQty/maxQty/minSaleQty/maxSaleQty conditions
     * or original qty if such value does not exist
     *
     * @param int|float $qty
     * @return int|float
     */
    public function suggestQty($qty)
    {
        // We do not manage stock
        if ($qty <= 0 || !$this->getManageStock()) {
            return $qty;
        }

        $qtyIncrements = (int) $this->getQtyIncrements(); // Currently only integer increments supported
        if ($qtyIncrements < 2) {
            return $qty;
        }

        $minQty = max($this->getMinSaleQty(), $qtyIncrements);
        $divisibleMin = ceil($minQty / $qtyIncrements) * $qtyIncrements;

        $maxQty = min($this->getQty() - $this->getMinQty(), $this->getMaxSaleQty());
        $divisibleMax = floor($maxQty / $qtyIncrements) * $qtyIncrements;

        if ($qty < $minQty || $qty > $maxQty || $divisibleMin > $divisibleMax) {
            // Do not perform rounding for qty that does not satisfy min/max conditions to not confuse customer
            return $qty;
        }

        // Suggest value closest to given qty
        $closestDivisibleLeft = floor($qty / $qtyIncrements) * $qtyIncrements;
        $closestDivisibleRight = $closestDivisibleLeft + $qtyIncrements;
        $acceptableLeft = min(max($divisibleMin, $closestDivisibleLeft), $divisibleMax);
        $acceptableRight = max(min($divisibleMax, $closestDivisibleRight), $divisibleMin);
        return abs($acceptableLeft - $qty) < abs($acceptableRight - $qty) ? $acceptableLeft : $acceptableRight;
    }

    /**
     * Checking quote item quantity
     *
     * Second parameter of this method specifies quantity of this product in whole shopping cart
     * which should be checked for stock availability
     *
     * @param mixed $qty quantity of this item (item qty x parent item qty)
     * @param mixed $summaryQty quantity of this product
     * @param mixed $origQty original qty of item (not multiplied on parent item qty)
     * @return \Maho\DataObject
     */
    public function checkQuoteItemQty($qty, $summaryQty, $origQty = 0)
    {
        $result = new \Maho\DataObject();
        $result->setHasError(false);

        if (!is_numeric($qty)) {
            $qty = Mage::app()->getLocale()->getNumber($qty);
        }

        /**
         * Check if child product assigned to parent
         */
        /** @var Mage_Sales_Model_Quote_Item $parentItem */
        $parentItem = $this->getParentItem();
        if ($this->getIsChildItem() && !empty($parentItem)) {
            $typeInstance = $parentItem->getProduct()->getTypeInstance(true);
            $requiredChildrenIds = $typeInstance->getChildrenIds($parentItem->getProductId(), true);
            $childrenIds = [];
            foreach ($requiredChildrenIds as $groupedChildrenIds) {
                $childrenIds = array_merge($childrenIds, $groupedChildrenIds);
            }
            if (!in_array($this->getProductId(), $childrenIds)) {
                $result->setHasError(true)
                    ->setMessage(Mage::helper('cataloginventory')
                        ->__('This product with current option is not available'))
                    ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products are not available'))
                    ->setQuoteMessageIndex('stock');
                return $result;
            }
        }

        /**
         * Check quantity type
         */
        $result->setItemIsQtyDecimal($this->getIsQtyDecimal());

        if (!$this->getIsQtyDecimal()) {
            $result->setHasQtyOptionUpdate(true);
            $qty = (int) $qty;

            /**
             * Adding stock data to quote item
             */
            $result->setItemQty($qty);

            $origQty = (int) $origQty;
            $result->setOrigQty($origQty);
        }

        if ($this->getMinSaleQty() && $qty < $this->getMinSaleQty()) {
            $result->setHasError(true)
                ->setMessage(
                    Mage::helper('cataloginventory')->__('The minimum quantity allowed for purchase is %s.', $this->getMinSaleQty() * 1),
                )
                ->setErrorCode('qty_min')
                ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products cannot be ordered in requested quantity.'))
                ->setQuoteMessageIndex('qty');
            return $result;
        }

        if ($this->getMaxSaleQty() > 0 && $qty > $this->getMaxSaleQty()) {
            $result->setHasError(true)
                ->setMessage(
                    Mage::helper('cataloginventory')->__('The maximum quantity allowed for purchase is %s.', $this->getMaxSaleQty() * 1),
                )
                ->setErrorCode('qty_max')
                ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products cannot be ordered in requested quantity.'))
                ->setQuoteMessageIndex('qty');
            return $result;
        }

        $result->addData($this->checkQtyIncrements($qty)->getData());
        if ($result->getHasError()) {
            return $result;
        }

        if (!$this->getManageStock()) {
            return $result;
        }

        if (!$this->getIsInStock()) {
            $result->setHasError(true)
                ->setMessage(Mage::helper('cataloginventory')->__('This product is currently out of stock.'))
                ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products are currently out of stock.'))
                ->setQuoteMessageIndex('stock');
            $result->setItemUseOldQty(true);
            return $result;
        }

        if (!$this->checkQty($summaryQty) || !$this->checkQty($qty)) {
            $message = Mage::helper('cataloginventory')->__('The requested quantity for "%s" is not available.', $this->getProductName());
            $result->setHasError(true)
                ->setMessage($message)
                ->setQuoteMessage($message)
                ->setQuoteMessageIndex('qty');
            return $result;
        }
        if (($this->getQty() - $summaryQty) < 0) {
            if ($this->getProductName()) {
                if ($this->getIsChildItem()) {
                    $backorderQty = ($this->getQty() > 0) ? ($summaryQty - $this->getQty()) * 1 : $qty * 1;
                    if ($backorderQty > $qty) {
                        $backorderQty = $qty;
                    }

                    $result->setItemBackorders($backorderQty);
                } else {
                    $orderedItems = $this->getOrderedItems();
                    $itemsLeft = ($this->getQty() > $orderedItems) ? ($this->getQty() - $orderedItems) * 1 : 0;
                    $backorderQty = ($itemsLeft > 0) ? ($qty - $itemsLeft) * 1 : $qty * 1;

                    if ($backorderQty > 0) {
                        $result->setItemBackorders($backorderQty);
                    }
                    $this->setOrderedItems($orderedItems + $qty);
                }

                if ($this->getBackorders() == Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY) {
                    if (!$this->getIsChildItem()) {
                        $result->setMessage(
                            Mage::helper('cataloginventory')->__('This product is not available in the requested quantity. %s of the items will be backordered.', ($backorderQty * 1)),
                        );
                    } else {
                        $result->setMessage(
                            Mage::helper('cataloginventory')->__('"%s" is not available in the requested quantity. %s of the items will be backordered.', $this->getProductName(), ($backorderQty * 1)),
                        );
                    }
                } elseif (Mage::app()->getStore()->isAdmin()) {
                    $result->setMessage(
                        Mage::helper('cataloginventory')->__('The requested quantity for "%s" is not available.', $this->getProductName()),
                    );
                }
            }
        } else {
            if (!$this->getIsChildItem()) {
                $this->setOrderedItems($qty + (int) $this->getOrderedItems());
            }
        }

        return $result;
    }

    /**
     * Check qty increments
     *
     * @param int|float $qty
     * @return \Maho\DataObject
     */
    public function checkQtyIncrements($qty)
    {
        $result = new \Maho\DataObject();
        if ($this->getSuppressCheckQtyIncrements()) {
            return $result;
        }

        $qtyIncrements = $this->getQtyIncrements();
        if ($qtyIncrements && (Mage::helper('core')->getExactDivision($qty, $qtyIncrements) != 0)) {
            $result->setHasError(true)
                ->setQuoteMessage(
                    Mage::helper('cataloginventory')->__('Some of the products cannot be ordered in the requested quantity.'),
                )
                ->setErrorCode('qty_increments')
                ->setQuoteMessageIndex('qty');
            if ($this->getIsChildItem()) {
                $result->setMessage(
                    Mage::helper('cataloginventory')->__('%s is available for purchase in increments of %s only.', $this->getProductName(), $qtyIncrements * 1),
                );
            } else {
                $result->setMessage(
                    Mage::helper('cataloginventory')->__('This product is available for purchase in increments of %s only.', $qtyIncrements * 1),
                );
            }
        }

        return $result;
    }

    /**
     * Add join for catalog in stock field to product collection
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return $this
     */
    public function addCatalogInventoryToProductCollection($productCollection)
    {
        $this->_getResource()->addCatalogInventoryToProductCollection($productCollection);
        return $this;
    }

    /**
     * Add error to Quote Item
     *
     * @param string $itemError
     * @param string $quoteError
     * @param string $errorIndex
     * @return $this
     */
    protected function _addQuoteItemError(
        Mage_Sales_Model_Quote_Item $item,
        $itemError,
        $quoteError,
        $errorIndex = 'error',
    ) {
        $item->setHasError();
        $item->setMessage($itemError);
        $item->setQuoteMessage($quoteError);
        $item->setQuoteMessageIndex($errorIndex);
        return $this;
    }

    /**
     * Before save prepare process
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        // see if quantity is defined for this item type
        $typeId = $this->getTypeId();
        if ($productTypeId = $this->getProductTypeId()) {
            $typeId = $productTypeId;
        }

        $isQty = Mage::helper('cataloginventory')->isQty($typeId);

        if ($isQty) {
            if (!$this->verifyStock()) {
                $this->setIsInStock(false)
                    ->setStockStatusChangedAutomaticallyFlag();
            } elseif (
                !$this->_getData('is_in_stock')
                && Mage::getStoreConfigFlag(self::XML_PATH_SYNC_AVAIL_WITH_QTY)
            ) {
                $this->setIsInStock();
            }

            // if qty is below notify qty, update the low stock date to today date otherwise set null
            $this->setLowStockDate(null);
            if ($this->verifyNotification()) {
                $this->setLowStockDate(
                    new DateTime()->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
                );
            }

            $this->setStockStatusChangedAutomatically(0);
            if ($this->hasStockStatusChangedAutomaticallyFlag()) {
                $this->setStockStatusChangedAutomatically((int) $this->getStockStatusChangedAutomaticallyFlag());
            }
        } else {
            $this->setQty(0);
        }

        if (!$this->hasData('stock_id')) {
            $this->setStockId($this->getStockId());
        }

        return $this;
    }

    /**
     * Chceck if item should be in stock or out of stock based on $qty param of existing item qty
     *
     * @param float|null $qty
     * @return bool true - item in stock | false - item out of stock
     */
    public function verifyStock($qty = null)
    {
        $qty ??= $this->getQty();
        if ($this->getBackorders() == Mage_CatalogInventory_Model_Stock::BACKORDERS_NO && $qty <= $this->getMinQty()) {
            return false;
        }
        return true;
    }

    /**
     * Check if item qty require stock status notification
     *
     * @param float | null $qty
     * @return bool (true - if require, false - if not require)
     */
    public function verifyNotification($qty = null)
    {
        $qty ??= $this->getQty();
        return (float) $qty < $this->getNotifyStockQty();
    }

    /**
     * Retrieve Stock Availability
     *
     * @return bool|int
     */
    public function getIsInStock()
    {
        if (!$this->getManageStock()) {
            return true;
        }
        return $this->_getData('is_in_stock');
    }

    /**
     * Add product data to stock item
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function setProduct($product)
    {
        $this->setProductId($product->getId())
            ->setProductName($product->getName())
            ->setStoreId($product->getStoreId())
            ->setProductName($product->getName())
            ->setProductTypeId($product->getTypeId())
            ->setProductStatusChanged($product->dataHasChangedFor('status'))
            ->setProductChangedWebsites($product->getIsChangedWebsites());

        $this->_productInstance = $product;

        return $this;
    }

    /**
     * Returns product instance
     *
     * @return Mage_Catalog_Model_Product|null
     */
    public function getProduct()
    {
        return $this->_productInstance ?: $this->_getData('product');
    }

    /**
     * Retrieve stock qty whether product is composite or no
     *
     * @return float
     */
    public function getStockQty()
    {
        if (!$this->hasStockQty()) {
            $this->setStockQty(0);  // prevent possible recursive loop
            $product = $this->_productInstance;
            if (!$product || !$product->isComposite()) {
                $stockQty = $this->getQty();
            } else {
                $stockQty = null;
                $productsByGroups = $product->getTypeInstance(true)->getProductsToPurchaseByReqGroups($product);
                foreach ($productsByGroups as $productsInGroup) {
                    $qty = 0;
                    /** @var Mage_Catalog_Model_Product $childProduct */
                    foreach ($productsInGroup as $childProduct) {
                        if ($childProduct->hasStockItem()) {
                            $qty += $childProduct->getStockItem()->getStockQty();
                        }
                    }
                    if (is_null($stockQty) || $qty < $stockQty) {
                        $stockQty = $qty;
                    }
                }
            }
            $stockQty = (float) $stockQty;
            if ($stockQty < 0 || !$this->getManageStock()
                || !$this->getIsInStock() || ($product && !$product->isSaleable())
            ) {
                $stockQty = 0;
            }
            $this->setStockQty($stockQty);
        }
        return $this->getData('stock_qty');
    }

    /**
     * Reset model data
     * @return $this
     */
    public function reset()
    {
        if ($this->_productInstance) {
            $this->_productInstance = null;
        }
        return $this;
    }

    /**
     * Set whether index events should be processed immediately
     *
     * @param bool $process
     * @return $this
     */
    public function setProcessIndexEvents($process = true)
    {
        $this->_processIndexEvents = $process;
        return $this;
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

        if ($this->_processIndexEvents) {
            $indexer->processEntityAction($this, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
        } else {
            $indexer->logEvent($this, self::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
        }
        return $this;
    }

    /**
     * Get quantity correction with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getQtyCorrection(): ?float
    {
        $value = $this->getData('qty_correction');
        return $value !== null ? (float) $value : null;
    }

    public function setBackorders(?int $value): static
    {
        return $this->setData('backorders', $value);
    }

    public function setEnableQtyIncrements(?bool $value = true): static
    {
        return $this->setData('enable_qty_increments', $value);
    }

    public function getIsChildItem(): ?bool
    {
        $value = $this->getData('is_child_item');
        return $value === null ? null : (bool) $value;
    }

    public function setIsChildItem(?bool $value = true): static
    {
        return $this->setData('is_child_item', $value);
    }

    public function setIsInStock(?bool $value = true): static
    {
        return $this->setData('is_in_stock', $value);
    }

    public function getIsQtyDecimal(): ?bool
    {
        $value = $this->getData('is_qty_decimal');
        return $value === null ? null : (bool) $value;
    }

    public function setIsQtyDecimal(?bool $value = true): static
    {
        return $this->setData('is_qty_decimal', $value);
    }

    public function getLowStockDate(): ?string
    {
        $value = $this->getData('low_stock_date');
        return $value === null ? null : (string) $value;
    }

    public function setLowStockDate(?string $value): static
    {
        return $this->setData('low_stock_date', $value);
    }

    public function setManageStock(?bool $value = true): static
    {
        return $this->setData('manage_stock', $value);
    }

    public function setMaxSaleQty(?float $value): static
    {
        return $this->setData('max_sale_qty', $value);
    }

    public function setMinQty(?float $value): static
    {
        return $this->setData('min_qty', $value);
    }

    public function setMinSaleQty(?float $value): static
    {
        return $this->setData('min_sale_qty', $value);
    }

    public function setNotifyStockQty(?float $value): static
    {
        return $this->setData('notify_stock_qty', $value);
    }

    public function getOrderedItems(): ?float
    {
        $value = $this->getData('ordered_items');
        return $value === null ? null : (float) $value;
    }

    public function setOrderedItems(?float $value): static
    {
        return $this->setData('ordered_items', $value);
    }

    public function setParentItem(?Mage_Sales_Model_Quote_Item $value): static
    {
        return $this->setData('parent_item', $value);
    }

    public function setProductChangedWebsites(?bool $value = true): static
    {
        return $this->setData('product_changed_websites', $value);
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getProductName(): ?string
    {
        $value = $this->getData('product_name');
        return $value === null ? null : (string) $value;
    }

    public function setProductName(?string $value): static
    {
        return $this->setData('product_name', $value);
    }

    public function setProductStatusChanged(?bool $value = true): static
    {
        return $this->setData('product_status_changed', $value);
    }

    public function getProductTypeId(): ?string
    {
        $value = $this->getData('product_type_id');
        return $value === null ? null : (string) $value;
    }

    public function setProductTypeId(?string $value): static
    {
        return $this->setData('product_type_id', $value);
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

    public function setQtyIncrements(?float $value): static
    {
        return $this->setData('qty_increments', $value);
    }

    public function setStockId(?int $value): static
    {
        return $this->setData('stock_id', $value);
    }

    public function setStockQty(?float $value): static
    {
        return $this->setData('stock_qty', $value);
    }

    public function getStockStatus(): ?bool
    {
        $value = $this->getData('stock_status');
        return $value === null ? null : (bool) $value;
    }

    public function getStockStatusChangedAutomatically(): ?int
    {
        $value = $this->getData('stock_status_changed_automatically');
        return $value === null ? null : (int) $value;
    }

    public function setStockStatusChangedAutomatically(?int $value): static
    {
        return $this->setData('stock_status_changed_automatically', $value);
    }

    public function getStockStatusChangedAutomaticallyFlag(): ?bool
    {
        $value = $this->getData('stock_status_changed_automatically_flag');
        return $value === null ? null : (bool) $value;
    }

    public function setStockStatusChangedAutomaticallyFlag(?bool $value = true): static
    {
        return $this->setData('stock_status_changed_automatically_flag', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getSuppressCheckQtyIncrements(): ?bool
    {
        $value = $this->getData('suppress_check_qty_increments');
        return $value === null ? null : (bool) $value;
    }

    public function setSuppressCheckQtyIncrements(?bool $value = true): static
    {
        return $this->setData('suppress_check_qty_increments', $value);
    }

    public function getTypeId(): ?string
    {
        $value = $this->getData('type_id');
        return $value === null ? null : (string) $value;
    }

    public function getUseConfigBackorders(): ?bool
    {
        $value = $this->getData('use_config_backorders');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigBackorders(?bool $value = true): static
    {
        return $this->setData('use_config_backorders', $value);
    }

    public function getUseConfigEnableQtyIncrements(): ?int
    {
        $value = $this->getData('use_config_enable_qty_increments');
        return $value === null ? null : (int) $value;
    }

    public function setUseConfigEnableQtyIncrements(?int $value): static
    {
        return $this->setData('use_config_enable_qty_increments', $value);
    }

    public function getUseConfigManageStock(): ?bool
    {
        $value = $this->getData('use_config_manage_stock');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigManageStock(?bool $value = true): static
    {
        return $this->setData('use_config_manage_stock', $value);
    }

    public function getUseConfigMaxSaleQty(): ?bool
    {
        $value = $this->getData('use_config_max_sale_qty');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigMaxSaleQty(?bool $value = true): static
    {
        return $this->setData('use_config_max_sale_qty', $value);
    }

    public function getUseConfigMinQty(): ?bool
    {
        $value = $this->getData('use_config_min_qty');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigMinQty(?bool $value = true): static
    {
        return $this->setData('use_config_min_qty', $value);
    }

    public function getUseConfigMinSaleQty(): ?bool
    {
        $value = $this->getData('use_config_min_sale_qty');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigMinSaleQty(?bool $value = true): static
    {
        return $this->setData('use_config_min_sale_qty', $value);
    }

    public function getUseConfigNotifyStockQty(): ?bool
    {
        $value = $this->getData('use_config_notify_stock_qty');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigNotifyStockQty(?bool $value = true): static
    {
        return $this->setData('use_config_notify_stock_qty', $value);
    }

    public function getUseConfigQtyIncrements(): ?bool
    {
        $value = $this->getData('use_config_qty_increments');
        return $value === null ? null : (bool) $value;
    }

    public function setUseConfigQtyIncrements(?bool $value = true): static
    {
        return $this->setData('use_config_qty_increments', $value);
    }

}
