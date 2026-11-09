<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Model_Cart extends \Maho\DataObject implements Mage_Checkout_Model_Cart_Interface
{
    /**
     * Shopping cart items summary quantity(s)
     *
     * @var int|null
     */
    protected $_summaryQty;

    /**
     * List of product ids in shopping cart
     *
     * @var array|null
     */
    protected $_productIds;

    /**
     * Get shopping cart resource model
     *
     * @return Mage_Checkout_Model_Resource_Cart
     */
    protected function _getResource()
    {
        return Mage::getResourceSingleton('checkout/cart');
    }

    protected function _getService(): Mage_Checkout_Service_Cart
    {
        return Mage::getService('checkout/cart');
    }

    /**
     * Retrieve checkout session model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Retrieve customer session model
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * List of shopping cart items
     *
     * @return Mage_Sales_Model_Resource_Quote_Item_Collection|array
     */
    public function getItems()
    {
        if (!$this->getQuote()->getId()) {
            return [];
        }
        return $this->getQuote()->getItemsCollection();
    }

    /**
     * Retrieve array of cart product ids
     *
     * @return array
     */
    public function getQuoteProductIds()
    {
        $products = $this->getData('product_ids');
        if (is_null($products)) {
            $products = [];
            foreach ($this->getQuote()->getAllItems() as $item) {
                $productId = $item->getProductId();
                $products[$productId] = $productId;
            }
            $this->setData('product_ids', $products);
        }
        return $products;
    }

    /**
     * Get quote object associated with cart. By default it is current customer session quote
     *
     * @return Mage_Sales_Model_Quote
     */
    #[\Override]
    public function getQuote()
    {
        if (!$this->hasData('quote')) {
            $this->setData('quote', $this->getCheckoutSession()->getQuote());
        }
        return $this->_getData('quote');
    }

    /**
     * Set quote object associated with the cart
     *
     * @return $this
     */
    #[\Override]
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->setData('quote', $quote);
        return $this;
    }

    /**
     * Initialize cart quote state to be able use it on cart page
     *
     * @return $this
     */
    public function init()
    {
        $quote = $this->getQuote()->setCheckoutMethod('');

        if ($this->getCheckoutSession()->getCheckoutState() !== Mage_Checkout_Model_Session::CHECKOUT_STATE_BEGIN) {
            $quote->removeAllAddresses()->removePayment();
            $this->getCheckoutSession()->resetCheckout();
        }

        if (!$quote->hasItems()) {
            $quote->getShippingAddress()->setCollectShippingRates(false)
                ->removeAllShippingRates();
        }

        return $this;
    }

    /**
     * Convert order item to quote item
     *
     * @param Mage_Sales_Model_Order_Item $orderItem
     * @param mixed $qtyFlag if is null set product qty like in order
     * @return $this
     */
    public function addOrderItem($orderItem, $qtyFlag = null)
    {
        if (is_null($orderItem->getParentItem())) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($orderItem->getProductId());
            if (!$product->getId()) {
                return $this;
            }

            $info = $orderItem->getProductOptionByCode('info_buyRequest');
            $info = new \Maho\DataObject($info);
            if (is_null($qtyFlag)) {
                $info->setQty($orderItem->getQtyOrdered());
            } else {
                $info->setQty(1);
            }

            $this->addProduct($product, $info);
        }
        return $this;
    }

    /**
     * Get product object based on requested product information
     *
     * @param   mixed $productInfo
     * @return  Mage_Catalog_Model_Product
     */
    protected function _getProduct($productInfo)
    {
        if ($productInfo instanceof Mage_Catalog_Model_Product) {
            return $productInfo;
        }
        return $this->_getService()->getProductById((int) $productInfo, (int) Mage::app()->getStore()->getId());
    }

    /**
     * Get request for product add to cart procedure
     *
     * @param   mixed $requestInfo
     * @return \Maho\DataObject
     */
    protected function _getProductRequest($requestInfo)
    {
        if ($requestInfo instanceof \Maho\DataObject) {
            $request = $requestInfo;
        } elseif (is_numeric($requestInfo)) {
            $request = new \Maho\DataObject(['qty' => $requestInfo]);
        } else {
            $request = new \Maho\DataObject($requestInfo);
        }

        return $request;
    }

    /**
     * Add product to shopping cart (quote)
     *
     * @param   int|Mage_Catalog_Model_Product $productInfo
     * @param   mixed $requestInfo
     * @return  Mage_Checkout_Model_Cart
     */
    #[\Override]
    public function addProduct($productInfo, $requestInfo = null)
    {
        $product = $this->_getProduct($productInfo);
        $request = $this->_getProductRequest($requestInfo);

        try {
            $this->_getService()->addProduct($this->getQuote(), $product, $request);
        } catch (Mage_Core_Exception $e) {
            // A message on the exception means that the product refused the buy request: the shopper fixes it on the product page
            if ($e->getMessages()) {
                // A variant goes to the cart as its parent, so the shopper fixes the options on the page of the parent
                $cartProduct = $this->_getService()->getCartProduct($product, $request, (int) $this->getQuote()->getStoreId());
                $this->getCheckoutSession()->setRedirectUrl($product->hasOptionsValidationFail()
                    ? $cartProduct->getUrlModel()->getUrl($cartProduct, ['_query' => ['startcustomization' => 1]])
                    : $cartProduct->getProductUrl());
                if (!$e->getMessages(Mage_Core_Model_Message::NOTICE)) {
                    $this->getCheckoutSession()->setUseNotice(false);
                } elseif ($this->getCheckoutSession()->getUseNotice() === null) {
                    $this->getCheckoutSession()->setUseNotice();
                }
            }
            throw $e;
        }

        $this->getCheckoutSession()->setLastAddedProductId($product->getId());
        return $this;
    }

    /**
     * Adding products to cart by ids
     *
     * @param   array $productIds
     * @return  Mage_Checkout_Model_Cart
     */
    public function addProductsByIds($productIds)
    {
        $allAvailable = true;
        $allAdded     = true;

        if (!empty($productIds)) {
            foreach ($productIds as $productId) {
                $productId = (int) $productId;
                if (!$productId) {
                    continue;
                }
                $product = $this->_getProduct($productId);
                if ($product->isVisibleInCatalog()) {
                    try {
                        $this->_getService()->addProduct($this->getQuote(), $product, new \Maho\DataObject());
                    } catch (Exception) {
                        $allAdded = false;
                    }
                } else {
                    $allAvailable = false;
                }
            }

            if (!$allAvailable) {
                $this->getCheckoutSession()->addError(
                    Mage::helper('checkout')->__('Some of the requested products are unavailable.'),
                );
            }
            if (!$allAdded) {
                $this->getCheckoutSession()->addError(
                    Mage::helper('checkout')->__('Some of the requested products are not available in the desired quantity.'),
                );
            }
        }
        return $this;
    }

    /**
     * Returns suggested quantities for items.
     * Can be used to automatically fix user entered quantities before updating cart
     * so that cart contains valid qty values
     *
     * $data is an array of ($quoteItemId => (item info array with 'qty' key), ...)
     *
     * @param   array $data
     * @return  array
     */
    public function suggestItemsQty($data)
    {
        foreach ($data as $itemId => $itemInfo) {
            if (!isset($itemInfo['qty'])) {
                continue;
            }
            $qty = (float) $itemInfo['qty'];
            if ($qty <= 0) {
                continue;
            }

            $quoteItem = $this->getQuote()->getItemById($itemId);
            if (!$quoteItem) {
                continue;
            }

            $product = $quoteItem->getProduct();
            if (!$product) {
                continue;
            }

            $stockItem = $product->getStockItem();
            if (!$stockItem) {
                continue;
            }

            $data[$itemId]['before_suggest_qty'] = $qty;
            $data[$itemId]['qty'] = $stockItem->suggestQty($qty);
        }

        return $data;
    }

    /**
     * Update cart items information
     *
     * @param   array $data
     * @return  Mage_Checkout_Model_Cart
     */
    public function updateItems($data)
    {
        Mage::dispatchEvent('checkout_cart_update_items_before', ['cart' => $this, 'info' => $data]);

        $messageFactory = Mage::getSingleton('core/message');
        $session = $this->getCheckoutSession();
        $qtyRecalculatedFlag = false;
        $qtyByItemId = [];
        foreach ($data as $itemId => $itemInfo) {
            $item = $this->getQuote()->getItemById($itemId);
            if (!$item) {
                continue;
            }

            if (!empty($itemInfo['remove']) || (isset($itemInfo['qty']) && $itemInfo['qty'] == '0')) {
                $this->removeItem($itemId);
                continue;
            }

            $qty = isset($itemInfo['qty']) ? (float) $itemInfo['qty'] : 0;
            if ($qty > 0) {
                $qtyByItemId[(int) $itemId] = $qty;
                if (isset($itemInfo['before_suggest_qty']) && ($itemInfo['before_suggest_qty'] != $qty)) {
                    $qtyRecalculatedFlag = true;
                    $message = $messageFactory->notice(Mage::helper('checkout')->__('Quantity was recalculated from %d to %d', $itemInfo['before_suggest_qty'], $qty));
                    $session->addQuoteItemMessage($item->getId(), $message);
                }
            }
        }
        if ($qtyByItemId) {
            $this->_getService()->updateItems($this->getQuote(), $qtyByItemId);
        }

        if ($qtyRecalculatedFlag) {
            $session->addNotice(
                Mage::helper('checkout')->__('Some products quantities were recalculated because of quantity increment mismatch'),
            );
        }

        Mage::dispatchEvent('checkout_cart_update_items_after', ['cart' => $this, 'info' => $data]);
        return $this;
    }

    /**
     * Remove item from cart
     *
     * @param   int $itemId
     * @return  Mage_Checkout_Model_Cart
     */
    public function removeItem($itemId)
    {
        try {
            $this->_getService()->removeItem($this->getQuote(), (int) $itemId);
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Input) {
            // The cart has no such line, so the outcome is the same
        }
        return $this;
    }

    /**
     * Save cart
     *
     * @return $this
     */
    public function save()
    {
        Mage::dispatchEvent('checkout_cart_save_before', ['cart' => $this]);

        $this->getQuote()->getShippingAddress()->setCollectShippingRates();
        $this->_getService()->collectAndSave($this->getQuote());
        $this->getCheckoutSession()->setQuoteId($this->getQuote()->getId());
        /**
         * Cart save usually called after changes with cart items.
         */
        Mage::dispatchEvent('checkout_cart_save_after', ['cart' => $this]);
        return $this;
    }

    /**
     * Save cart (implement interface method)
     */
    #[\Override]
    public function saveQuote()
    {
        $this->save();
        return $this;
    }

    /**
     * Mark all quote items as deleted (empty shopping cart)
     *
     * @return $this
     */
    public function truncate()
    {
        $this->_getService()->truncate($this->getQuote());
        return $this;
    }

    /**
     * @return array|null
     */
    public function getProductIds()
    {
        if ($this->_productIds === null) {
            $this->_productIds = [];
            if ($this->getSummaryQty() > 0) {
                foreach ($this->getQuote()->getAllItems() as $item) {
                    $this->_productIds[] = $item->getProductId();
                }
            }
            $this->_productIds = array_unique($this->_productIds);
        }
        return $this->_productIds;
    }

    /**
     * Get shopping cart items summary (includes config settings)
     *
     * @return int|float
     */
    public function getSummaryQty()
    {
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();

        //If there is no quote id in session trying to load quote
        //and get new quote id. This is done for cases when quote was created
        //not by customer (from backend for example).
        if (!$quoteId && Mage::getSingleton('customer/session')->isLoggedIn()) {
            Mage::getSingleton('checkout/session')->getQuote();
            $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        }

        if ($quoteId && $this->_summaryQty === null) {
            if (Mage::getStoreConfig('checkout/cart_link/use_qty')) {
                $this->_summaryQty = $this->getItemsQty();
            } else {
                $this->_summaryQty = $this->getItemsCount();
            }
        }
        return $this->_summaryQty;
    }

    /**
     * Get shopping cart items count
     *
     * @return int
     */
    public function getItemsCount()
    {
        return $this->getQuote()->getItemsCount() * 1;
    }

    /**
     * Get shopping cart summary qty
     *
     * @return int|float
     */
    public function getItemsQty()
    {
        return $this->getQuote()->getItemsQty() * 1;
    }

    /**
     * Update item in shopping cart (quote)
     * $requestInfo - either qty (int) or buyRequest in form of array or \Maho\DataObject
     * $updatingParams - information on how to perform update, passed to Quote->updateItem() method
     *
     * @param int $itemId
     * @param int|array|\Maho\DataObject $requestInfo
     * @param null|array|\Maho\DataObject $updatingParams
     * @return Mage_Sales_Model_Quote_Item|string
     *
     * @see Mage_Sales_Model_Quote::updateItem()
     */
    public function updateItem($itemId, $requestInfo = null, $updatingParams = null)
    {
        $item = $this->getQuote()->getItemById($itemId);
        if (!$item) {
            $this->getCheckoutSession()->setUseNotice(false);
            Mage::throwException(Mage::helper('checkout')->__('Quote item does not exist.'));
        }
        $productId = $item->getProduct()->getId();

        try {
            $result = $this->_getService()->reconfigureItem(
                $this->getQuote(),
                (int) $itemId,
                $this->_getProductRequest($requestInfo),
                Mage_Checkout_Model_Actor::Customer,
                $updatingParams,
            );
        } catch (Mage_Core_Exception $e) {
            $this->getCheckoutSession()->setUseNotice(false);
            throw $e;
        }

        $this->getCheckoutSession()->setLastAddedProductId($productId);
        return $result;
    }
}
