<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Quote_Address _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address_Collection getCollection()
 *
 * @method $this unsAddressId()
 * @method $this unsAddressType()
 * @method $this unsBaseSubtotalInclTax()
 * @method bool hasPaymentMethod()
 * @method bool hasCouponCode()
 * @method $this unsSubtotalInclTax()
 */
class Mage_Sales_Model_Quote_Address extends Mage_Customer_Model_Address_Abstract
{
    /**
     * Default value for Destination street
     */
    public const DEFAULT_DEST_STREET = -1;

    /**
     * Prefix of model events
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'sales_quote_address';

    /**
     * Name of event object
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'quote_address';

    /**
     * Quote object
     *
     * @var Mage_Sales_Model_Resource_Quote_Address_Item_Collection|Mage_Sales_Model_Quote_Address_Item[]|null
     */
    protected $_items = null;

    /**
     * Quote object
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Sales Quote address rates
     *
     * @var Mage_Sales_Model_Resource_Quote_Address_Rate_Collection|Mage_Sales_Model_Quote_Address_Rate[]|null
     */
    protected $_rates = null;

    /**
     * Total models collector
     *
     * @var Mage_Sales_Model_Quote_Address_Total_Collector
     */
    protected $_totalCollector = null;

    /**
     * Total data as array
     *
     * @var array
     */
    protected $_totals = [];

    /**
     * Total amounts
     *
     * @var array
     */
    protected $_totalAmounts = [];

    /**
     * Total base amounts
     *
     * @var array
     */
    protected $_baseTotalAmounts = [];

    /**
     * Whether to segregate by nominal items only
     *
     * @var bool|null
     */
    protected $_nominalOnly = null;

    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_address');
    }

    /**
     * Init mapping array of short fields to its full names
     *
     * @return $this
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Initialize Quote identifier before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $this->_populateBeforeSaveData();
        return $this;
    }

    /**
     * Set the required fields
     */
    protected function _populateBeforeSaveData()
    {
        if ($this->getQuote()) {
            $this->_dataSaveAllowed = (bool) $this->getQuote()->getId();

            if ($this->getQuote()->getId()) {
                $this->setQuoteId($this->getQuote()->getId());
            }
            $this->setCustomerId($this->getQuote()->getCustomerId());

            /**
             * Init customer address id if customer address is assigned
             */
            if ($this->getCustomerAddress()) {
                $this->setCustomerAddressId($this->getCustomerAddress()->getId());
            }

            /**
             * Set same_as_billing to "1" when default shipping address is set as default
             * and it is not equal billing address
             */
            if (!$this->getId() && !$this->hasSameAsBilling()) {
                $this->setSameAsBilling($this->_isSameAsBilling());
            }
        }
    }

    /**
     * Returns true if the billing address is same as the shipping
     *
     * @return bool
     */
    protected function _isSameAsBilling()
    {
        return ($this->getAddressType() === self::TYPE_SHIPPING
            && ($this->_isNotRegisteredCustomer() || $this->_isDefaultShippingNullOrSameAsBillingAddress()));
    }

    /**
     * Checks if the user is a registered customer
     *
     * @return bool
     */
    protected function _isNotRegisteredCustomer()
    {
        return !$this->getQuote()->getCustomerId() || $this->getCustomerAddressId() === null;
    }

    /**
     * Returns true if the def billing address is same as customer address
     *
     * @return bool
     */
    protected function _isDefaultShippingNullOrSameAsBillingAddress()
    {
        $customer = $this->getQuote()->getCustomer();
        return !$customer->getDefaultShippingAddress()
            || $customer->getDefaultBillingAddress()
                && $customer->getDefaultBillingAddress()->getId() == $customer->getDefaultShippingAddress()->getId();
    }

    /**
     * Save child collections
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();
        if ($this->_items !== null) {
            $this->getItemsCollection()->save();
        }
        if ($this->_rates !== null) {
            $this->getShippingRatesCollection()->save();
        }
        return $this;
    }

    /**
     * Declare address quote model object
     *
     * @return  $this
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        if ($this->getQuoteId() != $quote->getId()) {
            $this->setQuoteId($quote->getId());
        }
        return $this;
    }

    /**
     * Retrieve quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (is_null($this->_quote)) {
            $this->_quote = Mage::getModel('sales/quote')->load($this->getQuoteId());
        }
        return $this->_quote;
    }

    /**
     * Import quote address data from customer address object
     *
     * @return  $this
     */
    public function importCustomerAddress(Mage_Customer_Model_Address $address)
    {
        Mage::helper('core')->copyFieldset('customer_address', 'to_quote_address', $address, $this);
        $email = null;
        if ($address->hasEmail()) {
            $email = $address->getEmail();
        } elseif ($address->getCustomer()) {
            $email = $address->getCustomer()->getEmail();
        }
        if ($email) {
            $this->setEmail($email);
        }
        return $this;
    }

    /**
     * Export data to customer address object
     *
     * @return Mage_Customer_Model_Address
     */
    public function exportCustomerAddress()
    {
        $address = Mage::getModel('customer/address');
        Mage::helper('core')->copyFieldset('sales_convert_quote_address', 'to_customer_address', $this, $address);
        return $address;
    }

    /**
     * Import address data from order address
     *
     * @return  $this
     */
    public function importOrderAddress(Mage_Sales_Model_Order_Address $address)
    {
        $this->setAddressType($address->getAddressType())
            ->setCustomerId($address->getCustomerId())
            ->setCustomerAddressId($address->getCustomerAddressId())
            ->setEmail($address->getEmail());

        Mage::helper('core')->copyFieldset('sales_convert_order_address', 'to_quote_address', $address, $this);

        return $this;
    }

    /**
     * Convert object to array
     *
     * @return  array
     */
    #[\Override]
    public function toArray(array $arrAttributes = [])
    {
        $arr = parent::toArray($arrAttributes);
        $arr['rates'] = $this->getShippingRatesCollection()->toArray($arrAttributes);
        $arr['items'] = $this->getItemsCollection()->toArray($arrAttributes);
        foreach ($this->getTotals() as $k => $total) {
            $arr['totals'][$k] = $total->toArray();
        }
        return $arr;
    }

    /**
     * Retrieve address items collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getItemsCollection()
    {
        if (is_null($this->_items)) {
            $this->_items = Mage::getModel('sales/quote_address_item')->getCollection()
                ->setAddressFilter($this->getId());

            if ($this->getId()) {
                foreach ($this->_items as $item) {
                    $item->setAddress($this);
                }
            }
        }
        return $this->_items;
    }

    /**
     * Get all available address items
     *
     * @return Mage_Sales_Model_Quote_Address_Item[]
     */
    public function getAllItems()
    {
        // We calculate item list once and cache it in three arrays - all items, nominal, non-nominal
        $cachedItems = $this->_nominalOnly ? 'nominal' : ($this->_nominalOnly === false ? 'nonnominal' : 'all');
        $key = 'cached_items_' . $cachedItems;
        if (!$this->hasData($key)) {
            // For compatibility  we will use $this->_filterNominal to divide nominal items from non-nominal
            // (because it can be overloaded)
            // So keep current flag $this->_nominalOnly and restore it after cycle
            $wasNominal = $this->_nominalOnly;
            $this->_nominalOnly = true; // Now $this->_filterNominal() will return positive values for nominal items

            $quoteItems = $this->getQuote()->getItemsCollection();

            $items = [];
            $nominalItems = [];
            $nonNominalItems = [];
            /*
            * For virtual quote we assign items only to billing address, otherwise - only to shipping address
            */
            $addressType = $this->getAddressType();
            $canAddItems = $this->getQuote()->isVirtual()
                ? ($addressType == self::TYPE_BILLING)
                : ($addressType == self::TYPE_SHIPPING);

            if ($canAddItems) {
                foreach ($quoteItems as $qItem) {
                    if ($qItem->isDeleted()) {
                        continue;
                    }
                    $items[] = $qItem;
                    if ($this->_filterNominal($qItem)) {
                        $nominalItems[] = $qItem;
                    } else {
                        $nonNominalItems[] = $qItem;
                    }
                }
            }

            // Cache calculated lists
            $this->setData('cached_items_all', $items);
            $this->setData('cached_items_nominal', $nominalItems);
            $this->setData('cached_items_nonnominal', $nonNominalItems);

            $this->_nominalOnly = $wasNominal; // Restore original value before we changed it
        }

        $items = $this->getData($key);
        return $items;
    }

    /**
     * Getter for all non-nominal items
     *
     * @return array
     */
    public function getAllNonNominalItems()
    {
        $this->_nominalOnly = false;
        $result = $this->getAllItems();
        $this->_nominalOnly = null;
        return $result;
    }

    /**
     * Getter for all nominal items
     *
     * @return Mage_Sales_Model_Quote_Address_Item[]
     */
    public function getAllNominalItems()
    {
        $this->_nominalOnly = true;
        $result = $this->getAllItems();
        $this->_nominalOnly = null;
        return $result;
    }

    /**
     * Segregate by nominal criteria
     *
     * true: get nominals only
     * false: get non-nominals only
     * null: get all
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return Mage_Sales_Model_Quote_Item_Abstract|false
     */
    protected function _filterNominal($item)
    {
        return ($this->_nominalOnly === null)
            || (($this->_nominalOnly === false) && !$item->isNominal())
            || (($this->_nominalOnly === true) && $item->isNominal())
            ? $item : false;
    }

    /**
     * Retrieve all visible items
     *
     * @return Mage_Sales_Model_Quote_Address_Item[]
     */
    public function getAllVisibleItems()
    {
        $items = [];
        foreach ($this->getAllItems() as $item) {
            if (!$item->getParentItemId()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Retrieve item quantity by id
     *
     * @param int $itemId
     * @return float|int
     */
    public function getItemQty($itemId = 0)
    {
        if ($this->hasData('item_qty')) {
            return $this->getData('item_qty');
        }

        $qty = 0;
        if ($itemId == 0) {
            foreach ($this->getAllItems() as $item) {
                $qty += $item->getQty();
            }
        } else {
            $item = $this->getItemById($itemId);
            if ($item) {
                $qty = $item->getQty();
            }
        }
        return $qty;
    }

    /**
     * Check Quote address has Items
     *
     * @return bool
     */
    public function hasItems()
    {
        return count($this->getAllItems()) > 0;
    }

    /**
     * Get address item object by id without
     *
     * @param int $itemId
     * @return Mage_Sales_Model_Quote_Address_Item|false
     */
    public function getItemById($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }
        return false;
    }

    /**
     * Get prepared not deleted item
     *
     * @param int $itemId
     * @return Mage_Sales_Model_Quote_Address_Item|false
     */
    public function getValidItemById($itemId)
    {
        foreach ($this->getAllItems() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }
        return false;
    }

    /**
     * Retrieve item object by quote item Id
     *
     * @param int $itemId
     * @return Mage_Sales_Model_Quote_Address_Item|false
     */
    public function getItemByQuoteItemId($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getQuoteItemId() == $itemId) {
                return $item;
            }
        }
        return false;
    }

    /**
     * Remove item from collection
     *
     * @param int $itemId
     * @return $this
     */
    public function removeItem($itemId)
    {
        $item = $this->getItemById($itemId);
        if ($item) {
            $item->isDeleted(true);
        }
        return $this;
    }

    /**
     * Add item to address
     *
     * @param   int $qty
     * @return  $this
     */
    public function addItem(Mage_Sales_Model_Quote_Item_Abstract $item, $qty = null)
    {
        if ($item instanceof Mage_Sales_Model_Quote_Item) {
            if ($item->getParentItemId()) {
                return $this;
            }
            $addressItem = Mage::getModel('sales/quote_address_item')
                ->setAddress($this)
                ->importQuoteItem($item);
            $this->getItemsCollection()->addItem($addressItem);

            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $addressChildItem = Mage::getModel('sales/quote_address_item')
                        ->setAddress($this)
                        ->importQuoteItem($child)
                        ->setParentItem($addressItem);
                    $this->getItemsCollection()->addItem($addressChildItem);
                }
            }
        } else {
            $addressItem = $item;
            $addressItem->setAddress($this);
            if (!$addressItem->getId()) {
                $this->getItemsCollection()->addItem($addressItem);
            }
        }

        if ($qty) {
            $addressItem->setQty($qty);
        }
        return $this;
    }

    /**
     * Retrieve collection of quote shipping rates
     *
     * @return Mage_Sales_Model_Resource_Quote_Address_Rate_Collection
     */
    public function getShippingRatesCollection()
    {
        if (is_null($this->_rates)) {
            $this->_rates = Mage::getModel('sales/quote_address_rate')->getCollection()
                ->setAddressFilter($this->getId());
            if ($this->getQuote()->hasNominalItems(false)) {
                $this->_rates->setFixedOnlyFilter(true);
            }
            if ($this->getId()) {
                foreach ($this->_rates as $rate) {
                    $rate->setAddress($this);
                }
            }
        }
        return $this->_rates;
    }

    /**
     * Retrieve all address shipping rates
     *
     * @return Mage_Sales_Model_Quote_Address_Rate[]
     */
    public function getAllShippingRates()
    {
        $rates = [];
        foreach ($this->getShippingRatesCollection() as $rate) {
            if (!$rate->isDeleted()) {
                $rates[] = $rate;
            }
        }
        return $rates;
    }

    /**
     * Retrieve all grouped shipping rates
     *
     * @return array
     */
    public function getGroupedAllShippingRates()
    {
        $rates = [];
        foreach ($this->getShippingRatesCollection() as $rate) {
            if (!$rate->isDeleted() && $rate->getCarrierInstance()) {
                $rates[$rate->getCarrier()] ??= [];
                $rates[$rate->getCarrier()][] = $rate;
                $rates[$rate->getCarrier()][0]->carrier_sort_order = $rate->getCarrierInstance()->getSortOrder();
            }
        }
        uasort($rates, $this->_sortRates(...));
        return $rates;
    }

    /**
     * Sort rates recursive callback
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    protected function _sortRates($a, $b)
    {
        if ((int) $a[0]->carrier_sort_order < (int) $b[0]->carrier_sort_order) {
            return -1;
        }
        if ((int) $a[0]->carrier_sort_order > (int) $b[0]->carrier_sort_order) {
            return 1;
        }
        return 0;
    }

    /**
     * Retrieve shipping rate by identifier
     *
     * @param   int $rateId
     * @return  Mage_Sales_Model_Quote_Address_Rate | false
     */
    public function getShippingRateById($rateId)
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            if ($rate->getId() == $rateId) {
                return $rate;
            }
        }
        return false;
    }

    /**
     * Retrieve shipping rate by code
     *
     * @param   string $code
     * @return  Mage_Sales_Model_Quote_Address_Rate|false
     */
    public function getShippingRateByCode($code)
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            // A rate removeAllShippingRates() marked deleted precedes the fresh
            // rate a refresh appended under the same code; skip it like
            // getAllShippingRates() does, or it shadows the live rate
            if ($rate->getCode() == $code && !$rate->isDeleted()) {
                return $rate;
            }
        }
        return false;
    }

    /**
     * Mark all shipping rates as deleted
     *
     * @return $this
     */
    public function removeAllShippingRates()
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            $rate->isDeleted(true);
        }
        return $this;
    }

    /**
     * Add shipping rate
     *
     * @return $this
     */
    public function addShippingRate(Mage_Sales_Model_Quote_Address_Rate $rate)
    {
        $rate->setAddress($this);
        $this->getShippingRatesCollection()->addItem($rate);
        return $this;
    }

    /**
     * Collecting shipping rates by address
     *
     * @return $this
     */
    public function collectShippingRates()
    {
        if (!$this->getCollectShippingRates()) {
            return $this;
        }

        $this->setCollectShippingRates(false);

        $this->removeAllShippingRates();

        if (!$this->getCountryId()) {
            return $this;
        }

        $found = $this->requestShippingRates();
        if (!$found) {
            $this->setShippingAmount(0)
                ->setBaseShippingAmount(0)
                ->setShippingMethod('')
                ->setShippingDescription('');
        }

        return $this;
    }

    /**
     * Request shipping rates for entire address or specified address item
     * Returns true if current selected shipping method code corresponds to one of the found rates
     *
     * @return bool
     */
    public function requestShippingRates(?Mage_Sales_Model_Quote_Item_Abstract $item = null)
    {
        /** @var Mage_Shipping_Model_Rate_Request $request */
        $request = Mage::getModel('shipping/rate_request');
        $request->setAllItems($item ? [$item] : $this->getAllItems());
        $request->setDestCountryId($this->getCountryId());
        $request->setDestRegionId($this->getRegionId());
        $request->setDestRegionCode($this->getRegionCode());
        /**
         * need to call getStreet with -1
         * to get data in string instead of array
         */
        $request->setDestStreet($this->getStreet(self::DEFAULT_DEST_STREET));
        $request->setDestCity($this->getCity());
        $request->setDestPostcode($this->getPostcode());
        $request->setPackageValue($item ? $item->getBaseRowTotal() : $this->getBaseSubtotal());
        $packageValueWithDiscount = $item
            ? $item->getBaseRowTotal() - $item->getBaseDiscountAmount()
            : $this->getBaseSubtotalWithDiscount();
        $request->setPackageValueWithDiscount($packageValueWithDiscount);
        $request->setPackageWeight($item ? $item->getRowWeight() : $this->getWeight());
        $request->setPackageQty($item ? $item->getQty() : $this->getItemQty());

        /**
         * Need for shipping methods that use insurance based on price of physical products
         */
        $packagePhysicalValue = $item
            ? $item->getBaseRowTotal()
            : $this->getBaseSubtotal() - $this->getBaseVirtualAmount();
        $request->setPackagePhysicalValue($packagePhysicalValue);

        $request->setFreeMethodWeight($item ? 0 : $this->getFreeMethodWeight());

        /**
         * Store and website identifiers need specify from quote
         */
        /*$request->setStoreId(Mage::app()->getStore()->getId());
        $request->setWebsiteId(Mage::app()->getStore()->getWebsiteId());*/

        $request->setStoreId($this->getQuote()->getStore()->getId());
        $request->setWebsiteId($this->getQuote()->getStore()->getWebsiteId());
        $request->setFreeShipping((bool) $this->getFreeShipping());
        /**
         * Currencies need to convert in free shipping
         */
        $request->setBaseCurrency($this->getQuote()->getStore()->getBaseCurrency());
        $request->setPackageCurrency($this->getQuote()->getStore()->getCurrentCurrency());
        $request->setLimitCarrier($this->getLimitCarrier());

        $request->setBaseSubtotalInclTax($this->getBaseSubtotalInclTax() + $this->getBaseExtraTaxAmount());

        $result = Mage::getModel('shipping/shipping')->collectRates($request)->getResult();

        $found = false;
        if ($result) {
            $shippingRates = $result->getAllRates();

            foreach ($shippingRates as $shippingRate) {
                $rate = Mage::getModel('sales/quote_address_rate')
                    ->importShippingRate($shippingRate);
                if (!$item) {
                    $this->addShippingRate($rate);
                }

                if ($this->getShippingMethod() == $rate->getCode()) {
                    if ($item) {
                        $item->setBaseShippingAmount($rate->getPrice());
                    } else {
                        /**
                         * possible bug: this should be setBaseShippingAmount(),
                         * see Mage_Sales_Model_Quote_Address_Total_Shipping::collect()
                         * where this value is set again from the current specified rate price
                         * (looks like a workaround for this bug)
                         */
                        $this->setShippingAmount($rate->getPrice());
                    }

                    $found = true;
                }
            }
        }
        return $found;
    }

    /**
     * Get totals collector model
     *
     * @return Mage_Sales_Model_Quote_Address_Total_Collector
     */
    public function getTotalCollector()
    {
        if ($this->_totalCollector === null) {
            $this->_totalCollector = Mage::getSingleton(
                'sales/quote_address_total_collector',
                ['store' => $this->getQuote()->getStore()],
            );
        }
        return $this->_totalCollector;
    }

    /**
     * Retrieve total models
     *
     * @return array
     */
    #[\Deprecated]
    public function getTotalModels()
    {
        return $this->getTotalCollector()->getRetrievers();
    }

    /**
     * Collect address totals
     *
     * @return $this
     */
    public function collectTotals()
    {
        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_before', [$this->_eventObject => $this]);
        foreach ($this->getTotalCollector()->getCollectors() as $model) {
            $model->collect($this);
        }
        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_after', [$this->_eventObject => $this]);
        return $this;
    }

    /**
     * Get address totals as array
     *
     * @return Mage_Sales_Model_Quote_Address_Total[]
     */
    public function getTotals()
    {
        // Reset totals before fetching to prevent stale entries from previous calls
        $this->_totals = [];

        foreach ($this->getTotalCollector()->getRetrievers() as $model) {
            $model->fetch($this);
        }
        return $this->_totals;
    }

    /**
     * Add total data or model
     *
     * @param Mage_Sales_Model_Quote_Address_Total|array $total
     * @return $this
     */
    public function addTotal($total)
    {
        if (is_array($total)) {
            $totalInstance = Mage::getModel('sales/quote_address_total')
                ->setData($total);
        } elseif ($total instanceof Mage_Sales_Model_Quote_Address_Total) {
            $totalInstance = $total;
        }

        if (isset($totalInstance)) {
            $totalInstance->setAddress($this);
            $this->_totals[$totalInstance->getCode()] = $totalInstance;
        }

        return $this;
    }

    /**
     * Rewrite clone method
     */
    public function __clone()
    {
        $this->setId(null);
    }

    /**
     * Validate minimum amount
     *
     * @return bool
     */
    public function validateMinimumAmount()
    {
        $storeId = $this->getQuote()->getStoreId();
        if (!Mage::getStoreConfigFlag('sales/minimum_order/active', $storeId)) {
            return true;
        }
        if ($this->getQuote()->getIsVirtual() && $this->getAddressType() == self::TYPE_SHIPPING) {
            return true;
        }

        if (!$this->getQuote()->getIsVirtual() && $this->getAddressType() != self::TYPE_SHIPPING) {
            return true;
        }

        $amount = Mage::getStoreConfig('sales/minimum_order/amount', $storeId);
        if ($this->getBaseSubtotalWithDiscount() < $amount) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve applied taxes
     *
     * @return array
     */
    public function getAppliedTaxes()
    {
        $tax = $this->getData('applied_taxes');
        if (empty($tax)) {
            return [];
        }
        try {
            $return = Mage::helper('core/unserializeArray')->unserialize($tax);
        } catch (Exception) {
            $return = [];
        }
        return $return;
    }

    /**
     * Set applied taxes
     *
     * @param array $data
     * @return $this
     */
    public function setAppliedTaxes($data)
    {
        return $this->setData('applied_taxes', Mage::helper('core')->jsonEncode($data));
    }

    /**
     * Set shipping amount
     *
     * @param float $value
     * @param bool $alreadyExclTax
     * @return $this
     */
    public function setShippingAmount($value, $alreadyExclTax = false)
    {
        return $this->setData('shipping_amount', $value);
    }

    /**
     * Set base shipping amount
     *
     * @param float $value
     * @param bool $alreadyExclTax
     * @return $this
     */
    public function setBaseShippingAmount($value, $alreadyExclTax = false)
    {
        return $this->setData('base_shipping_amount', $value);
    }

    /**
     * Set total amount value
     *
     * @param   string $code
     * @param   float $amount
     * @return  $this
     */
    public function setTotalAmount($code, $amount)
    {
        $this->_totalAmounts[$code] = $amount;
        if ($code != 'subtotal') {
            $code = $code . '_amount';
        }
        $this->setData($code, $amount);
        return $this;
    }

    /**
     * Set total amount value in base store currency
     *
     * @param   string $code
     * @param   float $amount
     * @return  $this
     */
    public function setBaseTotalAmount($code, $amount)
    {
        $this->_baseTotalAmounts[$code] = $amount;
        if ($code != 'subtotal') {
            $code = $code . '_amount';
        }
        $this->setData('base_' . $code, $amount);
        return $this;
    }

    /**
     * Add amount total amount value
     *
     * @param   string $code
     * @param   float $amount
     * @return  $this
     */
    public function addTotalAmount($code, $amount)
    {
        $amount = $this->getTotalAmount($code) + $amount;
        $this->setTotalAmount($code, $amount);
        return $this;
    }

    /**
     * Add amount total amount value in base store currency
     *
     * @param   string $code
     * @param   float $amount
     * @return  $this
     */
    public function addBaseTotalAmount($code, $amount)
    {
        $amount = $this->getBaseTotalAmount($code) + $amount;
        $this->setBaseTotalAmount($code, $amount);
        return $this;
    }

    /**
     * Get total amount value by code
     *
     * @param   string $code
     * @return  float
     */
    public function getTotalAmount($code)
    {
        return $this->_totalAmounts[$code] ?? 0;
    }

    /**
     * Get total amount value by code in base store curncy
     *
     * @param   string $code
     * @return  float
     */
    public function getBaseTotalAmount($code)
    {
        return $this->_baseTotalAmounts[$code] ?? 0;
    }

    /**
     * Get all total amount values
     *
     * @return array
     */
    public function getAllTotalAmounts()
    {
        return $this->_totalAmounts;
    }

    /**
     * Get all total amount values in base currency
     *
     * @return array
     */
    public function getAllBaseTotalAmounts()
    {
        return $this->_baseTotalAmounts;
    }

    /**
     * Get subtotal amount with applied discount in base currency
     *
     * @return float
     */
    public function getBaseSubtotalWithDiscount()
    {
        return $this->getBaseSubtotal() + $this->getBaseDiscountAmount();
    }

    /**
     * Get subtotal amount with applied discount
     *
     * @return float
     */
    public function getSubtotalWithDiscount()
    {
        return $this->getSubtotal() + $this->getDiscountAmount();
    }

    public function getCouponCode(): string
    {
        return (string) $this->_getData('coupon_code');
    }

    /**
     * Get shipping amount with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getShippingAmount(): ?float
    {
        $value = $this->getData('shipping_amount');
        return $value !== null ? (float) $value : null;
    }

    public function getAddressType(): ?string
    {
        $value = $this->getData('address_type');
        return $value === null ? null : (string) $value;
    }

    public function setAddressType(?string $value): static
    {
        return $this->setData('address_type', $value);
    }

    public function getAppliedRuleIds(): ?string
    {
        $value = $this->getData('applied_rule_ids');
        return $value === null ? null : (string) $value;
    }

    public function setAppliedRuleIds(?string $value): static
    {
        return $this->setData('applied_rule_ids', $value);
    }

    public function getAppliedTaxesReset(): ?bool
    {
        $value = $this->getData('applied_taxes_reset');
        return $value === null ? null : (bool) $value;
    }

    public function setAppliedTaxesReset(?bool $value): static
    {
        return $this->setData('applied_taxes_reset', $value);
    }

    public function getBaseCustbalanceAmount(): ?float
    {
        $value = $this->getData('base_custbalance_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseCustbalanceAmount(?float $value): static
    {
        return $this->setData('base_custbalance_amount', $value);
    }

    public function getBaseDiscountAmount(): ?float
    {
        $value = $this->getData('base_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountAmount(?float $value): static
    {
        return $this->setData('base_discount_amount', $value);
    }

    public function getBaseExtraTaxAmount(): ?float
    {
        $value = $this->getData('base_extra_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseExtraTaxAmount(?float $value): static
    {
        return $this->setData('base_extra_tax_amount', $value);
    }

    public function getBaseGrandTotal(): ?float
    {
        $value = $this->getData('base_grand_total');
        return $value === null ? null : (float) $value;
    }

    public function setBaseGrandTotal(?float $value): static
    {
        return $this->setData('base_grand_total', $value);
    }

    public function getBaseHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_hidden_tax_amount', $value);
    }

    public function getBaseRowTotal(): ?float
    {
        $value = $this->getData('base_row_total');
        return $value === null ? null : (float) $value;
    }

    public function getBaseShippingAmount(): ?float
    {
        $value = $this->getData('base_shipping_amount');
        return $value === null ? null : (float) $value;
    }

    public function getBaseShippingAmountForDiscount(): ?float
    {
        $value = $this->getData('base_shipping_amount_for_discount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingAmountForDiscount(?float $value): static
    {
        return $this->setData('base_shipping_amount_for_discount', $value);
    }

    public function getBaseShippingDiscountAmount(): ?float
    {
        $value = $this->getData('base_shipping_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingDiscountAmount(?float $value): static
    {
        return $this->setData('base_shipping_discount_amount', $value);
    }

    public function getBaseShippingInclTax(): ?float
    {
        $value = $this->getData('base_shipping_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingInclTax(?float $value): static
    {
        return $this->setData('base_shipping_incl_tax', $value);
    }

    public function getBaseShippingHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_shipping_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_shipping_hidden_tax_amount', $value);
    }

    public function getBaseShippingTaxable(): ?float
    {
        $value = $this->getData('base_shipping_taxable');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingTaxable(?float $value): static
    {
        return $this->setData('base_shipping_taxable', $value);
    }

    public function getBaseShippingTaxAmount(): ?float
    {
        $value = $this->getData('base_shipping_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingTaxAmount(?float $value): static
    {
        return $this->setData('base_shipping_tax_amount', $value);
    }

    public function getBaseSubtotal(): ?float
    {
        $value = $this->getData('base_subtotal');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotal(?float $value): static
    {
        return $this->setData('base_subtotal', $value);
    }

    public function getBaseSubtotalInclTax(): ?float
    {
        $value = $this->getData('base_subtotal_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotalInclTax(?float $value): static
    {
        return $this->setData('base_subtotal_incl_tax', $value);
    }

    public function getBaseSubtotalTotalInclTax(): ?float
    {
        $value = $this->getData('base_subtotal_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotalTotalInclTax(?float $value): static
    {
        return $this->setData('base_subtotal_total_incl_tax', $value);
    }

    public function setBaseSubtotalWithDiscount(?float $value): static
    {
        return $this->setData('base_subtotal_with_discount', $value);
    }

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData('base_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxAmount(?float $value): static
    {
        return $this->setData('base_tax_amount', $value);
    }

    public function getBaseWeeeDiscount(): ?float
    {
        $value = $this->getData('base_weee_discount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeDiscount(?float $value): static
    {
        return $this->setData('base_weee_discount', $value);
    }

    public function getBaseVirtualAmount(): ?float
    {
        $value = $this->getData('base_virtual_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseVirtualAmount(?float $value): static
    {
        return $this->setData('base_virtual_amount', $value);
    }

    public function getCartFixedRules(): ?array
    {
        return $this->getData('cart_fixed_rules');
    }

    public function setCartFixedRules(?array $value): static
    {
        return $this->setData('cart_fixed_rules', $value);
    }

    public function getCollectShippingRates(): ?bool
    {
        $value = $this->getData('collect_shipping_rates');
        return $value === null ? null : (bool) $value;
    }

    public function setCollectShippingRates(?bool $value): static
    {
        return $this->setData('collect_shipping_rates', $value);
    }

    public function getCompany(): ?string
    {
        $value = $this->getData('company');
        return $value === null ? null : (string) $value;
    }

    public function setCompany(?string $value): static
    {
        return $this->setData('company', $value);
    }

    public function setCouponCode(?string $value): static
    {
        return $this->setData('coupon_code', $value);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }

    public function getCustbalanceAmount(): ?float
    {
        $value = $this->getData('custbalance_amount');
        return $value === null ? null : (float) $value;
    }

    public function setCustbalanceAmount(?float $value): static
    {
        return $this->setData('custbalance_amount', $value);
    }

    public function getCustomerAddress(): ?Mage_Customer_Model_Address
    {
        return $this->getData('customer_address');
    }

    public function setCustomerAddress(?Mage_Customer_Model_Address $value): static
    {
        return $this->setData('customer_address', $value);
    }

    public function getCustomerAddressId(): ?int
    {
        $value = $this->getData('customer_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerAddressId(?int $value): static
    {
        return $this->setData('customer_address_id', $value);
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getCustomerNotes(): ?string
    {
        $value = $this->getData('customer_notes');
        return $value === null ? null : (string) $value;
    }

    public function setCustomerNotes(?string $value): static
    {
        return $this->setData('customer_notes', $value);
    }

    public function getCustomerPassword(): ?string
    {
        $value = $this->getData('customer_password');
        return $value === null ? null : (string) $value;
    }

    public function setDeleteImmediately(?bool $value): static
    {
        return $this->setData('delete_immediately', $value);
    }

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
    }

    public function getDiscountDescription(): ?string
    {
        $value = $this->getData('discount_description');
        return $value === null ? null : (string) $value;
    }

    public function setDiscountDescription(?string $value): static
    {
        return $this->setData('discount_description', $value);
    }

    public function getDiscountDescriptionArray(): ?array
    {
        return $this->getData('discount_description_array');
    }

    public function setDiscountDescriptionArray(?array $value): static
    {
        return $this->setData('discount_description_array', $value);
    }

    public function getDiscountTaxCompensation(): ?float
    {
        $value = $this->getData('discount_tax_compensation');
        return $value === null ? null : (float) $value;
    }

    public function getDob(): ?string
    {
        $value = $this->getData('dob');
        return $value === null ? null : (string) $value;
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    public function getExtraTaxAmount(): ?float
    {
        $value = $this->getData('extra_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setExtraTaxAmount(?float $value): static
    {
        return $this->setData('extra_tax_amount', $value);
    }

    public function getFax(): ?string
    {
        $value = $this->getData('fax');
        return $value === null ? null : (string) $value;
    }

    public function setFax(?string $value): static
    {
        return $this->setData('fax', $value);
    }

    public function getFreeMethodWeight(): ?float
    {
        $value = $this->getData('free_method_weight');
        return $value === null ? null : (float) $value;
    }

    public function setFreeMethodWeight(?float $value): static
    {
        return $this->setData('free_method_weight', $value);
    }

    public function getFreeShipping(): ?bool
    {
        $value = $this->getData('free_shipping');
        return $value === null ? null : (bool) $value;
    }

    public function setFreeShipping(?bool $value): static
    {
        return $this->setData('free_shipping', $value);
    }

    public function getGender(): ?string
    {
        $value = $this->getData('gender');
        return $value === null ? null : (string) $value;
    }

    public function getGiftMessageId(): ?int
    {
        $value = $this->getData('gift_message_id');
        return $value === null ? null : (int) $value;
    }

    public function setGiftMessageId(?int $value): static
    {
        return $this->setData('gift_message_id', $value);
    }

    public function getGrandTotal(): ?float
    {
        $value = $this->getData('grand_total');
        return $value === null ? null : (float) $value;
    }

    public function setGrandTotal(?float $value): static
    {
        return $this->setData('grand_total', $value);
    }

    public function getHasChildren(): ?bool
    {
        $value = $this->getData('has_children');
        return $value === null ? null : (bool) $value;
    }

    public function getHiddenTaxAmount(): ?float
    {
        $value = $this->getData('hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxAmount(?float $value): static
    {
        return $this->setData('hidden_tax_amount', $value);
    }

    public function getIsShippingInclTax(): ?bool
    {
        $value = $this->getData('is_shipping_incl_tax');
        return $value === null ? null : (bool) $value;
    }

    public function setIsShippingInclTax(?bool $value): static
    {
        return $this->setData('is_shipping_incl_tax', $value);
    }

    public function setItemQty(?float $value): static
    {
        return $this->setData('item_qty', $value);
    }

    public function getLimitCarrier(): ?string
    {
        $value = $this->getData('limit_carrier');
        return $value === null ? null : (string) $value;
    }

    public function getParentItemId(): ?int
    {
        $value = $this->getData('parent_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setPaymentMethod(?string $value): static
    {
        return $this->setData('payment_method', $value);
    }

    public function setPrevQuoteCustomerGroupId(?int $value): static
    {
        return $this->setData('prev_quote_customer_group_id', $value);
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        return $this->getData('product');
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData('quote_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteId(?int $value): static
    {
        return $this->setData('quote_id', $value);
    }

    public function setRegionId(?int $value): static
    {
        return $this->setData('region_id', $value);
    }

    public function getRoundingDeltas(): ?array
    {
        return $this->getData('rounding_deltas');
    }

    public function setRoundingDeltas(?array $value): static
    {
        return $this->setData('rounding_deltas', $value);
    }

    public function getRowTotal(): ?float
    {
        $value = $this->getData('row_total');
        return $value === null ? null : (float) $value;
    }

    public function setRowWeight(?float $value): static
    {
        return $this->setData('row_weight', $value);
    }

    public function getSameAsBilling(): ?bool
    {
        $value = $this->getData('same_as_billing');
        return $value === null ? null : (bool) $value;
    }

    public function setSameAsBilling(?bool $value): static
    {
        return $this->setData('same_as_billing', $value);
    }

    public function getSaveInAddressBook(): ?bool
    {
        $value = $this->getData('save_in_address_book');
        return $value === null ? null : (bool) $value;
    }

    public function setSaveInAddressBook(?bool $value): static
    {
        return $this->setData('save_in_address_book', $value);
    }

    public function getShippingAmountForDiscount(): ?float
    {
        $value = $this->getData('shipping_amount_for_discount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingAmountForDiscount(?float $value): static
    {
        return $this->setData('shipping_amount_for_discount', $value);
    }

    public function getShippingDiscountAmount(): ?float
    {
        $value = $this->getData('shipping_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingDiscountAmount(?float $value): static
    {
        return $this->setData('shipping_discount_amount', $value);
    }

    public function getShippingDiscountPercent(): ?float
    {
        $value = $this->getData('shipping_discount_percent');
        return $value === null ? null : (float) $value;
    }

    public function setShippingDiscountPercent(?float $value): static
    {
        return $this->setData('shipping_discount_percent', $value);
    }

    public function getShippingDescription(): ?string
    {
        $value = $this->getData('shipping_description');
        return $value === null ? null : (string) $value;
    }

    public function setShippingDescription(?string $value): static
    {
        return $this->setData('shipping_description', $value);
    }

    public function getShippingHiddenTaxAmount(): ?float
    {
        $value = $this->getData('shipping_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingHiddenTaxAmount(?float $value): static
    {
        return $this->setData('shipping_hidden_tax_amount', $value);
    }

    public function getShippingInclTax(): ?float
    {
        $value = $this->getData('shipping_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setShippingInclTax(?float $value): static
    {
        return $this->setData('shipping_incl_tax', $value);
    }

    public function getShippingMethod(): ?string
    {
        $value = $this->getData('shipping_method');
        return $value === null ? null : (string) $value;
    }

    public function setShippingMethod(?string $value): static
    {
        return $this->setData('shipping_method', $value);
    }

    public function getShippingTaxable(): ?float
    {
        $value = $this->getData('shipping_taxable');
        return $value === null ? null : (float) $value;
    }

    public function setShippingTaxable(?float $value): static
    {
        return $this->setData('shipping_taxable', $value);
    }

    public function getShippingTaxAmount(): ?float
    {
        $value = $this->getData('shipping_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingTaxAmount(?float $value): static
    {
        return $this->setData('shipping_tax_amount', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function getSubtotal(): ?float
    {
        $value = $this->getData('subtotal');
        return $value === null ? null : (float) $value;
    }

    public function setSubtotal(?float $value): static
    {
        return $this->setData('subtotal', $value);
    }

    public function getSubtotalInclTax(): ?float
    {
        $value = $this->getData('subtotal_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setSubtotalInclTax(?float $value): static
    {
        return $this->setData('subtotal_incl_tax', $value);
    }

    public function setSubtotalWithDiscount(?float $value): static
    {
        return $this->setData('subtotal_with_discount', $value);
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
    }

    public function getTaxvat(): ?string
    {
        $value = $this->getData('taxvat');
        return $value === null ? null : (string) $value;
    }

    public function getTotalQty(): ?float
    {
        $value = $this->getData('total_qty');
        return $value === null ? null : (float) $value;
    }

    public function setTotalQty(?float $value): static
    {
        return $this->setData('total_qty', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $value): static
    {
        return $this->setData('updated_at', $value);
    }

    public function setVirtualAmount(?float $value): static
    {
        return $this->setData('virtual_amount', $value);
    }

    public function getWeeeDiscount(): ?float
    {
        $value = $this->getData('weee_discount');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeDiscount(?float $value): static
    {
        return $this->setData('weee_discount', $value);
    }

    public function getWeight(): ?float
    {
        $value = $this->getData('weight');
        return $value === null ? null : (float) $value;
    }

    public function setWeight(?float $value): static
    {
        return $this->setData('weight', $value);
    }

    public function getParentItem(): ?Mage_Sales_Model_Quote_Address
    {
        return $this->getData('parent_item');
    }

    public function getChildren(): ?array
    {
        return $this->getData('children');
    }
}
