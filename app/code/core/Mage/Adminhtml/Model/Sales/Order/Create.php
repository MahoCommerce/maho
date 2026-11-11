<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * Order create model
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_Sales_Order_Create extends \Maho\DataObject implements Mage_Checkout_Model_Cart_Interface
{
    /**
     * Quote session object
     *
     * @var Mage_Adminhtml_Model_Session_Quote
     */
    protected $_session;

    /**
     * Quote customer wishlist model object
     *
     * @var Mage_Wishlist_Model_Wishlist|false|null
     */
    protected $_wishlist;

    /**
     * Sales Quote instance
     *
     * @var Mage_Sales_Model_Quote|null
     */
    protected $_cart;

    /**
     * Catalog Compare List instance
     *
     * @var Mage_Catalog_Model_Product_Compare_List|false|null
     */
    protected $_compareList;

    /**
     * Re-collect quote flag
     *
     * @var bool
     */
    protected $_needCollect;

    /**
     * Re-collect cart flag
     *
     * @var bool
     */
    protected $_needCollectCart = false;

    /**
     * Collect (import) data and validate it flag
     *
     * @var bool
     */
    protected $_isValidate              = false;

    /**
     * Customer instance
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer;

    /**
     * Customer Address Form instance
     *
     * @var Mage_Customer_Model_Form|null
     */
    protected $_customerAddressForm;

    /**
     * Customer Form instance
     *
     * @var Mage_Customer_Model_Form|null
     */
    protected $_customerForm;

    /**
     * Array of validate errors
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Quote associated with the model
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    public function __construct()
    {
        $this->_session = Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Set validate data in import data flag
     *
     * @param bool $flag
     * @return $this
     */
    public function setIsValidate($flag)
    {
        $this->_isValidate = (bool) $flag;
        return $this;
    }

    /**
     * Return is validate data in import flag
     *
     * @return bool
     */
    public function getIsValidate()
    {
        return $this->_isValidate;
    }

    /**
     * Retrieve quote item
     *
     * @param   int|Mage_Sales_Model_Quote_Item $item
     * @return  Mage_Sales_Model_Quote_Item|false
     */
    protected function _getQuoteItem($item)
    {
        if ($item instanceof Mage_Sales_Model_Quote_Item) {
            return $item;
        }
        if (is_numeric($item)) {
            return $this->getSession()->getQuote()->getItemById($item);
        }
        return false;
    }

    /**
     * Initialize data for price rules
     *
     * @return $this
     */
    public function initRuleData()
    {
        Mage::unregister('rule_data');
        Mage::register('rule_data', new \Maho\DataObject([
            'store_id'  => $this->_session->getStore()->getId(),
            'website_id'  => $this->_session->getStore()->getWebsiteId(),
            'customer_group_id' => $this->getCustomerGroupId(),
        ]));
        return $this;
    }

    /**
     * Set collect totals flag for quote
     *
     * @param   bool $flag
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function setRecollect($flag)
    {
        $this->_needCollect = $flag;
        return $this;
    }

    /**
     * Recollect totals for customer cart.
     * Set recollect totals flag for quote
     *
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function recollectCart()
    {
        if ($this->_needCollectCart === true) {
            $this->getCustomerCart()
                ->collectTotals()
                ->save();
        }
        $this->setRecollect(true);
        return $this;
    }

    /**
     * Quote saving
     *
     * @return $this
     */
    #[\Override]
    public function saveQuote()
    {
        if (!$this->getQuote()->getId()) {
            return $this;
        }

        if ($this->_needCollect) {
            $this->getQuote()->collectTotals();
        }

        $this->getQuote()->save();
        return $this;
    }

    /**
     * Retrieve session model object of quote
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     * Retrieve quote object model
     *
     * @return Mage_Sales_Model_Quote
     */
    #[\Override]
    public function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getSession()->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Set quote object
     *
     * @return $this
     */
    #[\Override]
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * Initialize creation data from existing order
     *
     * @return $this
     */
    public function initFromOrder(Mage_Sales_Model_Order $order)
    {
        $session = $this->getSession();
        if (!$order->getReordered()) {
            $session->setOrderId($order->getId());
        } else {
            $session->setReordered($order->getId());
        }

        /**
         * Check if we edit guest order
         */
        $session->setCurrencyId($order->getOrderCurrencyCode());
        if ($order->getCustomerId()) {
            $session->setCustomerId($order->getCustomerId());
        } else {
            $session->setCustomerId(null);
            $session->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            $session->setCustomerIsGuest();
        }

        $session->setStoreId($order->getStoreId());

        //Notify other modules about the session quote
        Mage::dispatchEvent(
            'init_from_order_session_quote_initialized',
            ['session_quote' => $session],
        );

        /**
         * Initialize catalog rule data with new session values
         */
        $this->initRuleData();

        $itemsCollection = $order->getItemsCollection(
            array_keys(Mage::getConfig()->getNode('adminhtml/sales/order/create/available_product_types')->asArray()),
            true,
        );

        foreach ($itemsCollection as $orderItem) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            if (!$orderItem->getParentItem()) {
                if ($order->getReordered()) {
                    $qty = $orderItem->getQtyOrdered();
                } else {
                    $qty = $orderItem->getQtyOrdered() - $orderItem->getQtyShipped() - $orderItem->getQtyInvoiced();
                }

                if ($qty > 0) {
                    try {
                        $this->initFromOrderItem($orderItem, $qty);
                    } catch (Mage_Core_Exception $e) {
                        $session->addError($e->getMessage());
                    }
                }
            }
        }

        $orderShippingAddress = $order->getShippingAddress();
        if ($orderShippingAddress) {
            $addressDiff = array_diff_assoc($orderShippingAddress->getData(), $order->getBillingAddress()->getData());
            unset($addressDiff['address_type'], $addressDiff['entity_id']);
            $orderShippingAddress->setSameAsBilling(empty($addressDiff));
        }

        $this->_initBillingAddressFromOrder($order);
        $this->_initShippingAddressFromOrder($order);

        $quote = $this->getQuote();
        if (!$quote->isVirtual() && $this->getShippingAddress()->getSameAsBilling()) {
            $this->setShippingAsBilling(1);
        }

        $this->setShippingMethod($order->getShippingMethod());
        $quote->getShippingAddress()->setShippingDescription($order->getShippingDescription());

        $quote->getPayment()->addData($order->getPayment()->getData());

        $orderCouponCode = $order->getCouponCode();
        if ($orderCouponCode) {
            $quote->setCouponCode($orderCouponCode);
        }

        if ($quote->getCouponCode()) {
            $quote->collectTotals();
        }

        Mage::helper('core')->copyFieldset(
            'sales_copy_order',
            'to_edit',
            $order,
            $quote,
        );

        Mage::dispatchEvent('sales_convert_order_to_quote', [
            'order' => $order,
            'quote' => $quote,
        ]);

        if (!$order->getCustomerId()) {
            $quote->setCustomerIsGuest();
        }

        if ($session->getUseOldShippingMethod(true)) {
            /*
             * if we are making reorder or editing old order
             * we need to show old shipping as preselected
             * so for this we need to collect shipping rates
             */
            $this->collectShippingRates();
        } else {
            /*
             * if we are creating new order then we don't need to collect
             * shipping rates before customer hit appropriate button
             */
            $this->collectRates();
        }

        // Make collect rates when user click "Get shipping methods and rates" in order creating
        // $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
        // $this->getQuote()->getShippingAddress()->collectShippingRates();

        $quote->save();

        return $this;
    }

    protected function _initBillingAddressFromOrder(Mage_Sales_Model_Order $order)
    {
        $this->getQuote()->getBillingAddress()->setCustomerAddressId(null);
        Mage::helper('core')->copyFieldset(
            'sales_copy_order_billing_address',
            'to_order',
            $order->getBillingAddress(),
            $this->getQuote()->getBillingAddress(),
        );
    }

    protected function _initShippingAddressFromOrder(Mage_Sales_Model_Order $order)
    {
        $orderShippingAddress = $order->getShippingAddress();
        $quoteShippingAddress = $this->getQuote()->getShippingAddress()
            ->setCustomerAddressId(null)
            ->setSameAsBilling($orderShippingAddress && $orderShippingAddress->getSameAsBilling());
        Mage::helper('core')->copyFieldset(
            'sales_copy_order_shipping_address',
            'to_order',
            $orderShippingAddress,
            $quoteShippingAddress,
        );
    }

    /**
     * Add the product of $orderItem to the quote, with the options of the order item.
     *
     * @param int $qty
     * @return Mage_Sales_Model_Quote_Item|$this
     * @throws Mage_Core_Exception when the cart refuses the product
     */
    public function initFromOrderItem(Mage_Sales_Model_Order_Item $orderItem, $qty = null)
    {
        if (!$orderItem->getId()) {
            return $this;
        }

        if ($orderItem->isCustomItem()) {
            $price = Mage::helper('tax')->priceIncludesTax($this->getQuote()->getStore())
                ? $orderItem->getPriceInclTax()
                : $orderItem->getPrice();
            $item = $this->addCustomItem(
                (string) $orderItem->getName(),
                (float) $price,
                (int) $orderItem->getTaxClassId(),
                !$orderItem->getIsVirtual(),
                (float) $orderItem->getWeight(),
            );
            if (is_numeric($qty)) {
                $item->setQty((float) $qty);
            }
            Mage::dispatchEvent('sales_convert_order_item_to_quote_item', [
                'order_item' => $orderItem,
                'quote_item' => $item,
            ]);
            return $item;
        }

        $product = Mage::getModel('catalog/product')
            ->setStoreId($this->getSession()->getStoreId())
            ->load($orderItem->getProductId());

        if ($product->getId()) {
            $product->setSkipCheckRequiredOption();
            $buyRequest = $orderItem->getBuyRequest();
            if (is_numeric($qty)) {
                $buyRequest->setQty($qty);
            }
            $item = Mage::getService('checkout/cart')->addProduct(
                $this->getQuote(),
                $product,
                $buyRequest,
                Mage_Checkout_Model_Actor::Staff,
                save: false,
            );

            if ($additionalOptions = $orderItem->getProductOptionByCode('additional_options')) {
                $item->addOption(new \Maho\DataObject(
                    [
                        'product' => $item->getProduct(),
                        'code' => 'additional_options',
                        'value' => Mage::helper('core')->jsonEncode($additionalOptions),
                    ],
                ));
            }

            Mage::dispatchEvent('sales_convert_order_item_to_quote_item', [
                'order_item' => $orderItem,
                'quote_item' => $item,
            ]);
            return $item;
        }

        return $this;
    }

    /**
     * Retrieve customer wishlist model object
     *
     * @params bool $cacheReload pass cached wishlist object and get new one
     * @return Mage_Wishlist_Model_Wishlist
     */
    public function getCustomerWishlist($cacheReload = false)
    {
        if (!is_null($this->_wishlist) && !$cacheReload) {
            return $this->_wishlist;
        }

        if ($this->getSession()->getCustomer()->getId()) {
            $this->_wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer(
                $this->getSession()->getCustomer(),
                true,
            );
            $this->_wishlist->setStore($this->getSession()->getStore())
                ->setSharedStoreIds($this->getSession()->getStore()->getWebsite()->getStoreIds());
        } else {
            $this->_wishlist = false;
        }

        return $this->_wishlist;
    }

    /**
     * Retrieve customer cart quote object model
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getCustomerCart()
    {
        if (!is_null($this->_cart)) {
            return $this->_cart;
        }

        $this->_cart = Mage::getModel('sales/quote');

        if ($this->getSession()->getCustomer()->getId()) {
            $this->_cart->setStore($this->getSession()->getStore())
                ->loadByCustomer($this->getSession()->getCustomer()->getId());
            if (!$this->_cart->getId()) {
                $this->_cart->assignCustomer($this->getSession()->getCustomer());
                $this->_cart->save();
            }
        }

        return $this->_cart;
    }

    /**
     * Retrieve customer compare list model object
     *
     * @return Mage_Catalog_Model_Product_Compare_List
     */
    public function getCustomerCompareList()
    {
        if (!is_null($this->_compareList)) {
            return $this->_compareList;
        }

        if ($this->getSession()->getCustomer()->getId()) {
            $this->_compareList = Mage::getModel('catalog/product_compare_list');
        } else {
            $this->_compareList = false;
        }
        return $this->_compareList;
    }

    public function getCustomerGroupId()
    {
        $groupId = $this->getQuote()->getCustomerGroupId();
        if (!$groupId) {
            $groupId = $this->getSession()->getCustomerGroupId();
        }
        return $groupId;
    }

    /**
     * Move quote item to another items list
     *
     * @param   int|Mage_Sales_Model_Quote_Item $item
     * @param   string $moveTo
     * @param   int|float|null $qty null moves the full quantity of the item
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function moveQuoteItem($item, $moveTo, $qty = null)
    {
        $item = $this->_getQuoteItem($item);
        if ($item) {
            $qty ??= $item->getQty();
            $removeItem = false;
            $moveTo = explode('_', $moveTo);
            switch ($moveTo[0]) {
                case 'order':
                    $info = $item->getBuyRequest();
                    $info->setOptions($this->_prepareOptionsForRequest($item))
                        ->setQty($qty);

                    $product = Mage::getModel('catalog/product')
                        ->setStoreId($this->getQuote()->getStoreId())
                        ->load($item->getProduct()->getId());

                    $product->setSkipCheckRequiredOption();
                    $newItem = Mage::getService('checkout/cart')->addProduct(
                        $this->getQuote(),
                        $product,
                        $info,
                        Mage_Checkout_Model_Actor::Staff,
                        save: false,
                    );
                    $product->unsSkipCheckRequiredOption();
                    $newItem->checkData();
                    $this->_needCollectCart = true;
                    break;
                case 'cart':
                    $cart = $this->getCustomerCart();
                    if ($cart && !$item->isCustomItem() && is_null($item->getOptionByCode('additional_options'))) {
                        //options and info buy request
                        $product = Mage::getModel('catalog/product')
                            ->setStoreId($this->getQuote()->getStoreId())
                            ->load($item->getProduct()->getId());

                        $info = $item->getOptionByCode('info_buyRequest');
                        if ($info) {
                            $info = new \Maho\DataObject(
                                Mage::helper('core/string')->unserialize($info->getValue()),
                            );
                            $info->setQty($qty);
                            $info->setOptions($this->_prepareOptionsForRequest($item));
                        } else {
                            $info = new \Maho\DataObject([
                                'product_id' => $product->getId(),
                                'qty' => $qty,
                                'options' => $this->_prepareOptionsForRequest($item),
                            ]);
                        }

                        $cartItem = Mage::getService('checkout/cart')->addProduct(
                            $cart,
                            $product,
                            $info,
                            Mage_Checkout_Model_Actor::Staff,
                            save: false,
                        );
                        if ($cartItem->getParentItem()) {
                            $cartItem = $cartItem->getParentItem();
                        }
                        $cartItem->setPrice($item->getProduct()->getPrice());
                        $cart->setTotalsCollectedFlag(false);
                        $this->_needCollectCart = true;
                        $removeItem = true;
                    }
                    break;
                case 'wishlist':
                    if (isset($moveTo[1])) {
                        $wishlist = Mage::getModel('wishlist/wishlist')
                            ->load($moveTo[1])
                            ->setStore($this->getSession()->getStore())
                            ->setSharedStoreIds($this->getSession()->getStore()->getWebsite()->getStoreIds());
                    } else {
                        $wishlist = $this->getCustomerWishlist();
                    }
                    if (!$wishlist || !$wishlist->getId() || $wishlist->getCustomerId() != $this->getSession()->getCustomerId()) {
                        Mage::throwException(Mage::helper('wishlist')->__('Could not find wishlist'));
                    }
                    if ($item->getProduct()->isVisibleInSiteVisibility()) {
                        $info = $item->getBuyRequest();
                        $info->setOptions($this->_prepareOptionsForRequest($item))
                            ->setQty($qty)
                            ->setStoreId($this->getSession()->getStoreId());
                        $wishlist->addNewItem($item->getProduct(), $info)
                            ->save();
                        $removeItem = true;
                    }
                    break;
                case 'remove':
                    $removeItem = true;
                    break;
                default:
                    break;
            }
            if ($removeItem) {
                $this->getQuote()->deleteItem($item);
            }
            $this->setRecollect(true);
        }
        return $this;
    }

    /**
     * Handle data sent from sidebar
     *
     * @param array $data
     * @return $this
     */
    public function applySidebarData($data)
    {
        if (isset($data['add_order_item'])) {
            foreach ($data['add_order_item'] as $orderItemId => $value) {
                /** @var Mage_Sales_Model_Order_Item $orderItem */
                $orderItem = Mage::getModel('sales/order_item')->load($orderItemId);
                try {
                    $this->initFromOrderItem($orderItem);
                } catch (Mage_Core_Exception $e) {
                    $this->getSession()->addError($e->getMessage());
                }
            }
        }
        if (isset($data['add_cart_item'])) {
            foreach ($data['add_cart_item'] as $itemId => $qty) {
                $item = $this->getCustomerCart()->getItemById($itemId);
                if ($item) {
                    try {
                        $this->moveQuoteItem($item, 'order', $qty);
                    } catch (Mage_Core_Exception $e) {
                        $this->getSession()->addError($e->getMessage());
                        continue;
                    }
                    $this->removeItem($itemId, 'cart');
                }
            }
        }
        if (isset($data['add_wishlist_item'])) {
            foreach ($data['add_wishlist_item'] as $itemId => $qty) {
                $item = Mage::getModel('wishlist/item')
                    ->loadWithOptions($itemId, 'info_buyRequest');
                if ($item->getId()) {
                    $this->addProduct($item->getProduct(), $item->getBuyRequest()->toArray());
                    $this->removeItem($itemId, 'wishlist');
                }
            }
        }
        if (isset($data['add'])) {
            foreach ($data['add'] as $productId => $qty) {
                $this->addProduct($productId, ['qty' => $qty]);
            }
        }
        if (isset($data['remove'])) {
            foreach ($data['remove'] as $itemId => $from) {
                $this->removeItem($itemId, $from);
            }
        }
        if (isset($data['empty_customer_cart']) && (int) $data['empty_customer_cart'] == 1) {
            $this->getCustomerCart()->removeAllItems()->collectTotals()->save();
        }
        return $this;
    }

    /**
     * Remove item from some of customer items storage (shopping cart, wishlist etc.)
     *
     * @param   int $itemId
     * @param   string $from
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function removeItem($itemId, $from)
    {
        switch ($from) {
            case 'quote':
                $this->removeQuoteItem($itemId);
                break;
            case 'cart':
                if ($cart = $this->getCustomerCart()) {
                    $cart->removeItem($itemId)
                        ->collectTotals()
                        ->save();
                }
                break;
            case 'wishlist':
                if ($wishlist = $this->getCustomerWishlist()) {
                    $wishlist->removeItem($itemId)
                        ->save();
                }
                break;
            case 'compared':
                Mage::getModel('catalog/product_compare_item')
                    ->load($itemId)
                    ->delete();
                break;
        }
        return $this;
    }

    /**
     * Remove quote item
     *
     * @param   int $item
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function removeQuoteItem($item)
    {
        $this->getQuote()->removeItem($item);
        $this->setRecollect(true);
        return $this;
    }

    /**
     * Add product to current order quote
     * $product can be either product id or product model
     * $config can be either buyRequest config, or just qty
     *
     * @param   int|Mage_Catalog_Model_Product $product
     * @param float|array|\Maho\DataObject $config
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    #[\Override]
    public function addProduct($product, $config = 1)
    {
        if (!is_array($config) && !($config instanceof \Maho\DataObject)) {
            $config = ['qty' => $config];
        }
        $config = new \Maho\DataObject($config);

        $service = Mage::getService('checkout/cart');
        if (!($product instanceof Mage_Catalog_Model_Product)) {
            $product = $service->getProductById((int) $product, (int) $this->getSession()->getStoreId())
                ->setStore($this->getSession()->getStore());
        }

        $stockItem = $product->getStockItem();
        if ($stockItem && $stockItem->getIsQtyDecimal()) {
            $product->setIsQtyDecimal();
        } elseif ($config->hasQty()) {
            $config->setQty((int) $config->getQty());
        }
        if ($config->hasQty() && $config->getQty() <= 0) {
            $config->setQty(1);
        }

        $product->setCartQty((float) $config->getQty());
        try {
            $item = $service->addProduct($this->getQuote(), $product, $config, Mage_Checkout_Model_Actor::Staff);
        } catch (Mage_Core_Exception $e) {
            // A product that misses options goes to the order anyway: the grid then opens its configure popup
            if (!$e->getMessages(Mage_Core_Model_Message::NOTICE)
                || $product->getTypeId() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE
            ) {
                throw $e;
            }
            $item = $this->getQuote()->addProductAdvanced(
                $service->getCartProduct($product, $config, (int) $this->getQuote()->getStoreId()),
                $config,
                Mage_Catalog_Model_Product_Type_Abstract::PROCESS_MODE_LITE,
            );
            if (is_string($item)) {
                Mage::throwException($item);
            }
        }
        $item->checkData();

        $this->setRecollect(true);
        return $this;
    }

    /**
     * Add multiple products to current order quote
     *
     * @return  Mage_Adminhtml_Model_Sales_Order_Create|Exception
     */
    public function addProducts(array $products)
    {
        foreach ($products as $productId => $config) {
            $config['qty'] = isset($config['qty']) ? (float) $config['qty'] : 1;
            try {
                $this->addProduct($productId, $config);
            } catch (Mage_Core_Exception $e) {
                $this->getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                return $e;
            }
        }
        return $this;
    }

    /**
     * Add a custom line to the order quote: a name, a price and a tax class, with no catalog product.
     *
     * @throws Mage_Core_Exception
     */
    public function addCustomItem(
        string $name,
        float $price,
        int $taxClassId,
        bool $requiresShipping,
        float $weight,
    ): Mage_Sales_Model_Quote_Item {
        $helper = Mage::helper('sales');
        $name = trim($name);
        if ($name === '') {
            Mage::throwException($helper->__('Enter a name for the custom item.'));
        }
        if ($price < 0) {
            Mage::throwException($helper->__('The price of the custom item cannot be negative.'));
        }
        if ($taxClassId !== 0) {
            $taxClass = Mage::getModel('tax/class')->load($taxClassId);
            if (!$taxClass->getId() || $taxClass->getClassType() !== Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT) {
                Mage::throwException($helper->__('The tax class of the custom item does not exist.'));
            }
        }
        if ($requiresShipping && $weight <= 0) {
            Mage::throwException($helper->__('Enter a weight for a custom item that requires shipping.'));
        }

        $quote = $this->getQuote();
        $item = Mage::getModel('sales/quote_item');
        $item->setQuote($quote)
            ->setStoreId((int) $quote->getStoreId())
            ->setName($name)
            ->setProductType($requiresShipping
                ? Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
                : Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
            ->setWeight($requiresShipping ? $weight : 0.0)
            ->setTaxClassId($taxClassId)
            ->setCustomPrice($price)
            ->setOriginalCustomPrice($price);
        $item->getProduct();
        $item->setQty(1);

        // addProduct() would merge this line into another one, since both have no product id.
        $quote->addItem($item);
        $this->setRecollect(true);

        return $item;
    }

    /**
     * Update quantity of order quote items
     *
     * @param   array $data
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function updateQuoteItems($data)
    {
        if (is_array($data)) {
            try {
                foreach ($data as $itemId => $info) {
                    if (!empty($info['configured'])) {
                        $item = $this->getQuote()->updateItem($itemId, new \Maho\DataObject($info));
                        $itemQty = (float) $item->getQty();
                    } else {
                        $item       = $this->getQuote()->getItemById($itemId);
                        $itemQty    = (float) $info['qty'];
                    }

                    if ($item) {
                        if ($item->getProduct()->getStockItem()) {
                            if (!$item->getProduct()->getStockItem()->getIsQtyDecimal()) {
                                $itemQty = (int) $itemQty;
                            } else {
                                $item->setIsQtyDecimal();
                            }
                        }
                        $itemQty    = $itemQty > 0 ? $itemQty : 1;
                        if (isset($info['custom_price'])) {
                            $itemPrice  = $this->_parseCustomPrice($info['custom_price']);
                        } elseif ($item->isCustomItem()) {
                            // A custom line has no catalog price to go back to, so it keeps its own price
                            $itemPrice = $item->getOriginalCustomPrice();
                        } else {
                            $itemPrice = null;
                        }
                        $noDiscount = !isset($info['use_discount']);

                        if (empty($info['action']) || !empty($info['configured'])) {
                            $item->setQty($itemQty);
                            $currentPrice = $item->getOriginalCustomPrice();
                            if (($itemPrice === null) !== ($currentPrice === null) || (float) $itemPrice !== (float) $currentPrice) {
                                // A new configuration can give a new line, which has no ID before the save
                                if (!$item->getId()) {
                                    $this->getQuote()->save();
                                }
                                Mage::getService('checkout/cart')->setCustomPrice(
                                    $this->getQuote(),
                                    (int) $item->getId(),
                                    $itemPrice === null ? null : (float) $itemPrice,
                                    Mage_Checkout_Model_Actor::Staff,
                                );
                            }
                            $item->setNoDiscount($noDiscount);
                            $item->getProduct()->setIsSuperMode();
                            $item->getProduct()->unsSkipCheckRequiredOption();
                            $item->checkData();
                        }
                        if (!empty($info['action'])) {
                            $this->moveQuoteItem($item, $info['action'], $itemQty);
                        }
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $this->recollectCart();
                throw $e;
            } catch (Exception $e) {
                Mage::logException($e);
            }
            $this->recollectCart();
        }
        return $this;
    }

    /**
     * Parse additional options and sync them with product options
     *
     * @param string $additionalOptions
     * @return array
     */
    protected function _parseOptions(Mage_Sales_Model_Quote_Item $item, $additionalOptions)
    {
        $productOptions = Mage::getSingleton('catalog/product_option_type_default')
            ->setProduct($item->getProduct())
            ->getProductOptions();

        $newOptions = [];
        $newAdditionalOptions = [];

        foreach (explode("\n", $additionalOptions) as $additionalOption) {
            if (strlen(trim($additionalOption))) {
                try {
                    if (!str_contains($additionalOption, ':')) {
                        Mage::throwException(
                            Mage::helper('adminhtml')->__('There is an error in one of the option rows.'),
                        );
                    }
                    [$label, $value] = explode(':', $additionalOption, 2);
                } catch (Exception) {
                    Mage::throwException(Mage::helper('adminhtml')->__('There is an error in one of the option rows.'));
                }
                $label = trim($label);
                $value = trim($value);
                if (empty($value)) {
                    continue;
                }

                if (array_key_exists($label, $productOptions)) {
                    $optionId = $productOptions[$label]['option_id'];
                    $option = $item->getProduct()->getOptionById($optionId);

                    $group = Mage::getSingleton('catalog/product_option')->groupFactory($option->getType())
                        ->setOption($option)
                        ->setProduct($item->getProduct());

                    $parsedValue = $group->parseOptionValue($value, $productOptions[$label]['values']);

                    if ($parsedValue !== null) {
                        $newOptions[$optionId] = $parsedValue;
                    } else {
                        $newAdditionalOptions[] = [
                            'label' => $label,
                            'value' => $value,
                        ];
                    }
                } else {
                    $newAdditionalOptions[] = [
                        'label' => $label,
                        'value' => $value,
                    ];
                }
            }
        }

        return [
            'options' => $newOptions,
            'additional_options' => $newAdditionalOptions,
        ];
    }

    /**
     * Assign options to item
     *
     * @param array $options
     * @return $this
     */
    protected function _assignOptionsToItem(Mage_Sales_Model_Quote_Item $item, $options)
    {
        if ($optionIds = $item->getOptionByCode('option_ids')) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                $item->removeOption('option_' . $optionId);
            }
            $item->removeOption('option_ids');
        }
        if ($item->getOptionByCode('additional_options')) {
            $item->removeOption('additional_options');
        }
        $item->save();
        if (!empty($options['options'])) {
            $item->addOption(new \Maho\DataObject(
                [
                    'product' => $item->getProduct(),
                    'code' => 'option_ids',
                    'value' => implode(',', array_keys($options['options'])),
                ],
            ));

            foreach ($options['options'] as $optionId => $optionValue) {
                $item->addOption(new \Maho\DataObject(
                    [
                        'product' => $item->getProduct(),
                        'code' => 'option_' . $optionId,
                        'value' => $optionValue,
                    ],
                ));
            }
        }
        if (!empty($options['additional_options'])) {
            $item->addOption(new \Maho\DataObject(
                [
                    'product' => $item->getProduct(),
                    'code' => 'additional_options',
                    'value' => Mage::helper('core')->jsonEncode($options['additional_options']),
                ],
            ));
        }

        return $this;
    }

    /**
     * Prepare options array for info buy request
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return array
     */
    protected function _prepareOptionsForRequest($item)
    {
        $newInfoOptions = [];
        if ($optionIds = $item->getOptionByCode('option_ids')) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                $option = $item->getProduct()->getOptionById($optionId);
                $optionValue = $item->getOptionByCode('option_' . $optionId)->getValue();

                $group = Mage::getSingleton('catalog/product_option')->groupFactory($option->getType())
                    ->setOption($option)
                    ->setQuoteItem($item);

                $newInfoOptions[$optionId] = $group->prepareOptionValueForRequest($optionValue);
            }
        }
        return $newInfoOptions;
    }

    protected function _parseCustomPrice($price)
    {
        $price = Mage::app()->getLocale()->getNumber($price);
        $price = $price > 0 ? $price : 0;
        return $price;
    }

    /**
     * Retrieve oreder quote shipping address
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getShippingAddress()
    {
        return $this->getQuote()->getShippingAddress();
    }

    /**
     * Return Customer (Checkout) Form instance
     *
     * @return Mage_Customer_Model_Form
     */
    protected function _getCustomerForm()
    {
        $this->_customerForm ??= Mage::getModel('customer/form')
            ->setFormCode('adminhtml_checkout')
            ->ignoreInvisible(false);
        return $this->_customerForm;
    }

    /**
     * Return Customer Address Form instance
     *
     * @return Mage_Customer_Model_Form
     */
    protected function _getCustomerAddressForm()
    {
        $this->_customerAddressForm ??= Mage::getModel('customer/form')
            ->setFormCode('adminhtml_customer_address')
            ->ignoreInvisible(false);
        return $this->_customerAddressForm;
    }

    /**
     * Put the fields of the address form of $data into $address.
     *
     * @return $this
     */
    protected function _setQuoteAddress(Mage_Sales_Model_Quote_Address $address, array $data)
    {
        $addressForm    = $this->_getCustomerAddressForm()
            ->setEntity($address)
            ->setEntityType(Mage::getSingleton('eav/config')->getEntityType('customer_address'))
            ->setIsAjaxRequest(!$this->getIsValidate());

        // prepare request
        // save original request structure for files
        if ($address->getAddressType() == Mage_Sales_Model_Quote_Address::TYPE_SHIPPING) {
            $requestData  = ['order' => ['shipping_address' => $data]];
            $requestScope = 'order/shipping_address';
        } else {
            $requestData = ['order' => ['billing_address' => $data]];
            $requestScope = 'order/billing_address';
        }
        $request        = $addressForm->prepareRequest($requestData);
        $addressData    = $addressForm->extractData($request, $requestScope);
        // Mage_Sales_Service_Order::validateQuote() checks the address when the order is placed
        if ($this->getIsValidate()) {
            $addressForm->compactData($addressData);
        } else {
            $addressForm->restoreData($addressData);
        }

        return $this;
    }

    /**
     * @param array|Mage_Sales_Model_Quote_Address $address
     * @return $this
     */
    public function setShippingAddress($address)
    {
        if (is_array($address)) {
            $address['save_in_address_book'] = isset($address['save_in_address_book'])
                && !empty($address['save_in_address_book']);
            $shippingAddress = Mage::getModel('sales/quote_address')
                ->setData($address)
                ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING);
            if (!$this->getQuote()->isVirtual()) {
                $this->_setQuoteAddress($shippingAddress, $address);
            }
            $shippingAddress->implodeStreetAddress();
        }
        if ($address instanceof Mage_Sales_Model_Quote_Address) {
            $shippingAddress = $address;
        }

        $this->setRecollect(true);
        $this->getQuote()->setShippingAddress($shippingAddress);
        return $this;
    }

    public function setShippingAsBilling($flag)
    {
        /** @var Mage_Checkout_Service_Cart $cartService */
        $cartService = Mage::getService('checkout/cart');
        // The form sends the flag on each reload, and a copy without a change needs no save of the cart
        if ($flag && !$cartService->isShippingSameAsBilling($this->getQuote())) {
            $cartService->useBillingForShipping($this->getQuote());
        } elseif (!$flag) {
            $this->getShippingAddress()->setSameAsBilling(false);
        }
        $this->setRecollect(true);
        return $this;
    }

    /**
     * Retrieve quote billing address
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getBillingAddress()
    {
        return $this->getQuote()->getBillingAddress();
    }

    /**
     * @return $this
     */
    public function setBillingAddress(array|Mage_Sales_Model_Quote_Address $address)
    {
        if (is_array($address)) {
            $address['save_in_address_book'] = isset($address['save_in_address_book']) ? 1 : 0;
            $billingAddress = Mage::getModel('sales/quote_address')
                ->setData($address)
                ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING);
            $this->_setQuoteAddress($billingAddress, $address);
            $billingAddress->implodeStreetAddress();
        } else {
            $billingAddress = $address;
        }

        if ($this->getShippingAddress()->getSameAsBilling()) {
            $shippingAddress = clone $billingAddress;
            $shippingAddress->setSameAsBilling();
            $shippingAddress->setSaveInAddressBook(false);
            $address['save_in_address_book'] = 0;
            $this->setShippingAddress($address);
        }

        $this->getQuote()->setBillingAddress($billingAddress);
        return $this;
    }

    public function setShippingMethod($method)
    {
        $this->getShippingAddress()->setShippingMethod($method);
        $this->setRecollect(true);
        return $this;
    }

    public function resetShippingMethod()
    {
        $this->getShippingAddress()->setShippingMethod(null);
        $this->getShippingAddress()->removeAllShippingRates();
        return $this;
    }

    /**
     * Collect shipping data for quote shipping address
     */
    public function collectShippingRates()
    {
        $this->getQuote()->getShippingAddress()->setCollectShippingRates();
        $this->collectRates();
        return $this;
    }

    public function collectRates()
    {
        $this->getQuote()->collectTotals();
    }

    public function setPaymentMethod($method)
    {
        $this->getQuote()->getPayment()->setMethod($method);
        return $this;
    }

    /**
     * Give the quote the payment method and the payment data of $data, and save the quote.
     *
     * @throws Mage_Core_Exception when the method is not available for the quote, or refuses the data
     */
    public function setPaymentData($data)
    {
        $data['method'] ??= $this->getQuote()->getPayment()->getMethod();
        Mage::getService('checkout/cart')->selectPaymentMethod($this->getQuote(), $data);
        return $this;
    }

    public function applyCoupon($code)
    {
        $code = trim((string) $code);
        if ($code === '') {
            $this->getQuote()->setCouponCode('');
        } else {
            // The catalog price rules of the totals read the rule data
            $this->initRuleData();
            try {
                Mage::getService('checkout/cart')->applyCoupon($this->getQuote(), $code, Mage_Checkout_Model_Actor::Staff);
            } catch (Mage_Core_Exception) {
                // The cart keeps its coupon, and the controller tells that the code is not valid
            }
        }
        $this->setRecollect(true);
        return $this;
    }

    public function setAccountData($accountData)
    {
        $customer   = $this->getQuote()->getCustomer();
        $form       = $this->_getCustomerForm();
        $form->setEntity($customer);

        // emulate request
        $request = $form->prepareRequest($accountData);
        $data    = $form->extractData($request);
        $form->restoreData($data);

        $data = [];
        foreach ($form->getAttributes() as $attribute) {
            $code = sprintf('customer_%s', $attribute->getAttributeCode());
            $data[$code] = $customer->getData($attribute->getAttributeCode());
        }

        if ($this->getQuote()->getCustomerIsGuest()) {
            $data['customer_group_id'] = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        }

        if (isset($data['customer_group_id'])) {
            $groupModel = Mage::getModel('customer/group')->load($data['customer_group_id']);
            $data['customer_tax_class_id'] = $groupModel->getTaxClassId();
            $this->setRecollect(true);
        }

        $this->getQuote()->addData($data);
        return $this;
    }

    /**
     * Parse data retrieved from request
     *
     * @param   array $data
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    public function importPostData($data)
    {
        if (is_array($data)) {
            $this->addData($data);
        } else {
            return $this;
        }

        if (isset($data['account'])) {
            $this->setAccountData($data['account']);
        }

        if (isset($data['comment'])) {
            $this->getQuote()->addData($data['comment']);
            if (empty($data['comment']['customer_note_notify'])) {
                $this->getQuote()->setCustomerNoteNotify(false);
            } else {
                $this->getQuote()->setCustomerNoteNotify();
            }
        }

        if (isset($data['billing_address'])) {
            $this->setBillingAddress($data['billing_address']);
        }

        if (isset($data['shipping_address'])) {
            $this->setShippingAddress($data['shipping_address']);
        }

        if (isset($data['shipping_method'])) {
            $this->setShippingMethod($data['shipping_method']);
        }

        if (isset($data['payment_method'])) {
            $this->setPaymentMethod($data['payment_method']);
        }

        if (isset($data['coupon']['code'])) {
            $this->applyCoupon($data['coupon']['code']);
        }
        return $this;
    }

    /**
     * Check whether we need to create new customer (for another website) during order creation
     *
     * @param   Mage_Core_Model_Store $store
     * @return bool
     */
    protected function _customerIsInStore($store)
    {
        $customer = $this->getSession()->getCustomer();
        if ($customer->getWebsiteId() == $store->getWebsiteId()) {
            return true;
        }
        return $customer->isInStore($store);
    }

    /**
     * Set and validate Customer data
     *
     * @return $this
     */
    protected function _setCustomerData(Mage_Customer_Model_Customer $customer)
    {
        $form = $this->_getCustomerForm();
        $form->setEntity($customer);

        // emulate request
        $request = $form->prepareRequest(['order' => $this->getData()]);
        $data    = $form->extractData($request, 'order/account');
        if ($this->getIsValidate()) {
            $errors = $form->validateData($data);
            if ($errors !== true) {
                foreach ($errors as $error) {
                    $this->_errors[] = $error;
                }
                $form->restoreData($data);
            } else {
                $form->compactData($data);
            }
        } else {
            $form->restoreData($data);
        }

        return $this;
    }

    /**
     * Prepare quote customer
     *
     * @return $this
     */
    public function _prepareCustomer()
    {
        $quote = $this->getQuote();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $this->getSession()->getCustomer();
        /** @var Mage_Core_Model_Store $store */
        $store = $this->getSession()->getStore();

        $customerIsInStore = $this->_customerIsInStore($store);

        // Mage_Sales_Service_Order::submit() adds the addresses to the address book
        if ($customer->getId()) {
            // Create new customer if customer is not registered in specified store
            if (!$customerIsInStore) {
                $customer->setId(null)
                    ->unsetData('created_at')
                    ->setStore($store)
                    ->setDefaultBilling(null)
                    ->setDefaultShipping(null);
                $this->_setCustomerData($customer);
            }
        } else {
            // Prepare new customer
            $customer->addData($this->getBillingAddress()->exportCustomerAddress()->getData())
                ->setStore($store);
            $customer->setEmail($this->_getNewCustomerEmail($customer));
            $this->_setCustomerData($customer);
        }

        // Set quote customer data to customer
        $this->_setCustomerData($customer);

        // Set customer to quote and convert customer data to quote
        $quote->setCustomer($customer);

        // Add user defined attributes to quote
        $form = $this->_getCustomerForm()->setEntity($customer);
        foreach ($form->getUserAttributes() as $attribute) {
            $quoteCode = sprintf('customer_%s', $attribute->getAttributeCode());
            $quote->setData($quoteCode, $customer->getData($attribute->getAttributeCode()));
        }

        if ($customer->getId()) {
            // Restore account data for existing customer
            $this->_getCustomerForm()
                ->setEntity($customer)
                ->resetEntityData();
        } elseif ($customer->getGroupId() !== Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
            $quote->setCustomerIsNew();
        }

        return $this;
    }

    /**
     * Prepare item options
     */
    protected function _prepareQuoteItems()
    {
        foreach ($this->getQuote()->getAllItems() as $item) {
            $options = [];
            $productOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
            if ($productOptions) {
                $productOptions['info_buyRequest']['options'] = $this->_prepareOptionsForRequest($item);
                $options = $productOptions;
            }
            $item->setProductOrderOptions($options);
        }
        return $this;
    }

    /**
     * Create new order
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    public function createOrder()
    {
        $this->_prepareCustomer();
        $this->_validate();
        $quote = $this->getQuote();
        $this->_prepareQuoteItems();

        /** @var Mage_Sales_Service_Order $orderService */
        $orderService = Mage::getService('sales/order');
        $orderData = [];
        /** @var Mage_Sales_Model_Order $oldOrder */
        $oldOrder = $this->getSession()->getOrder();
        if ($oldOrder->getId()) {
            $originalId = $oldOrder->getOriginalIncrementId();
            if (!$originalId) {
                $originalId = $oldOrder->getIncrementId();
            }
            $orderData = [
                'original_increment_id'     => $originalId,
                'relation_parent_id'        => $oldOrder->getId(),
                'relation_parent_real_id'   => $oldOrder->getIncrementId(),
                'edit_increment'            => $oldOrder->getEditIncrement() + 1,
                'increment_id'              => $originalId . '-' . ($oldOrder->getEditIncrement() + 1),
            ];
            $quote->setReservedOrderId($orderData['increment_id']);

            $orderService->validateQuote($quote, Mage_Checkout_Model_Actor::Staff);
            if (!$oldOrder->canCancel()) {
                Mage::throwException('Could not cancel the old order during order edit.');
            }
        }

        $order = null;
        $placeOrder = function () use ($orderService, $quote, $orderData, &$order): void {
            $order = $orderService->submit($quote, Mage_Checkout_Model_Actor::Staff, null, $orderData, (bool) $this->getSendConfirmation());
        };
        if ($quote->getCustomerIsNew()) {
            /** @var Mage_Customer_Service_Customer $customerService */
            $customerService = Mage::getService('customer/customer');
            $storeId = (int) $quote->getStoreId();
            $customerService->create($quote->getCustomer(), $storeId, $storeId, $placeOrder);
        } else {
            $placeOrder();
        }
        /** @var Mage_Sales_Model_Order $order */
        if ($oldOrder->getId()) {
            // The old order and its payment stay as they are when the new order cannot be placed. The new order
            // exists now, so a cancel that fails must not stop the link between the two orders
            try {
                $orderService->cancel($oldOrder);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->getSession()->addError(Mage::helper('adminhtml')->__(
                    'The new order #%s was created, but the old order #%s was not canceled: %s',
                    $order->getIncrementId(),
                    $oldOrder->getIncrementId(),
                    $e->getMessage(),
                ));
            }
            $oldOrder->setRelationChildId((string) $order->getId());
            $oldOrder->setRelationChildRealId($order->getIncrementId());

            Mage::dispatchEvent('adminhtml_sales_order_create_save_before', ['new_order' => $order, 'old_order' => $oldOrder]);

            $oldOrder->save();
            $order->save();
        }

        return $order;
    }

    /**
     * Check the customer and the store of the order. Mage_Sales_Service_Order::validateQuote() checks the cart.
     *
     * @return $this
     */
    protected function _validate()
    {
        $customerId = $this->getSession()->getCustomerId();
        if (is_null($customerId)) {
            Mage::throwException(Mage::helper('adminhtml')->__('Please select a customer.'));
        }

        if (!$this->getSession()->getStore()->getId()) {
            Mage::throwException(Mage::helper('adminhtml')->__('Please select a store.'));
        }

        if (!empty($this->_errors)) {
            foreach ($this->_errors as $error) {
                $this->getSession()->addError($error);
            }
            Mage::throwException('');
        }
        return $this;
    }

    /**
     * Retrieve new customer email
     *
     * @param   Mage_Customer_Model_Customer $customer
     * @return  string
     */
    protected function _getNewCustomerEmail($customer)
    {
        $email = $this->getData('account/email');
        if (empty($email)) {
            $host = $this->getSession()
                ->getStore()
                ->getConfig(Mage_Customer_Model_Customer::XML_PATH_DEFAULT_EMAIL_DOMAIN);
            $account = $customer->getIncrementId() ?: time();
            $email = $account . '@' . $host;
            $account = $this->getData('account');
            $account['email'] = $email;
            $this->setData('account', $account);
        }
        return $email;
    }

    public function getSendConfirmation(): ?int
    {
        $value = $this->getData('send_confirmation');
        return $value === null ? null : (int) $value;
    }

}
