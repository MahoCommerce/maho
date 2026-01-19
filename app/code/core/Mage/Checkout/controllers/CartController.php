<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Checkout_CartController extends Mage_Core_Controller_Front_Action
{
    /**
     * Retrieve shopping cart model object
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }

    /**
     * Get checkout session model instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current active quote instance
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->_getCart()->getQuote();
    }

    /**
     * Set back redirect url to response
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _goBack()
    {
        $returnUrl = $this->getRequest()->getParam('return_url');
        if ($returnUrl) {
            if (!$this->_isUrlInternal($returnUrl)) {
                throw new Mage_Core_Exception('External urls redirect to "' . $returnUrl . '" denied!');
            }

            $this->_getSession()->getMessages(true);
            $this->getResponse()->setRedirect($returnUrl);
        } elseif (!Mage::getStoreConfig('checkout/cart/redirect_to_cart')
            && !$this->getRequest()->getParam('in_cart')
            && $backUrl = $this->_getRefererUrl()
        ) {
            $this->getResponse()->setRedirect($backUrl);
        } else {
            if ((strtolower($this->getRequest()->getActionName()) == 'add')
                && !$this->getRequest()->getParam('in_cart')
            ) {
                $this->_getSession()->setContinueShoppingUrl($this->_getRefererUrl());
            }
            $this->_redirect('checkout/cart');
        }
        return $this;
    }

    /**
     * Initialize product instance from request data
     *
     * @return Mage_Catalog_Model_Product|false
     */
    protected function _initProduct()
    {
        $productId = (int) $this->getRequest()->getParam('product');
        if ($productId) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productId);
            if ($product->getId()) {
                return $product;
            }
        }
        return false;
    }

    /**
     * Predispatch: remove isMultiShipping option from quote
     *
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        Mage::helper('catalog/product_flat')->disableFlatCollection(true);

        $cart = $this->_getCart();
        if ($cart->getQuote()->getIsMultiShipping()) {
            $cart->getQuote()->setIsMultiShipping(false);
        }

        return $this;
    }

    /**
     * Shopping cart display action
     */
    public function indexAction(): void
    {
        $cart = $this->_getCart();
        if ($cart->getQuote()->getItemsCount()) {
            $cart->init();
            if ($cart->getQuote()->getShippingAddress()
                && $this->_getSession()->getEstimatedShippingAddressData()
                && $couponCode = $this->_getSession()->getCartCouponCode()
            ) {
                $estimatedSessionAddressData = $this->_getSession()->getEstimatedShippingAddressData();
                $cart->getQuote()->getShippingAddress()
                    ->setCountryId($estimatedSessionAddressData['country_id'])
                    ->setCity($estimatedSessionAddressData['city'])
                    ->setPostcode($estimatedSessionAddressData['postcode'])
                    ->setRegionId($estimatedSessionAddressData['region_id'])
                    ->setRegion($estimatedSessionAddressData['region']);
                $cart->getQuote()->setCouponCode($couponCode);
            }
            $cart->save();

            if (!$this->_getQuote()->validateMinimumAmount()) {
                $minimumAmount = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())
                    ->format(Mage::getStoreConfig('sales/minimum_order/amount'));

                $warning = Mage::getStoreConfig('sales/minimum_order/description') ?: Mage::helper('checkout')->__('Minimum order amount is %s', $minimumAmount);

                $cart->getCheckoutSession()->addNotice($warning);
            }
        }

        // Compose array of messages to add
        $messages = [];
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }
        $cart->getCheckoutSession()->addUniqueMessages($messages);

        /**
         * if customer enteres shopping cart we should mark quote
         * as modified bc he can has checkout page in another window.
         */
        $this->_getSession()->setCartWasUpdated(true);

        \Maho\Profiler::start(__METHOD__ . 'cart_display');
        $this
            ->loadLayout()
            ->_initLayoutMessages('checkout/session')
            ->_initLayoutMessages('catalog/session')
            ->getLayout()->getBlock('head')->setTitle($this->__('Shopping Cart'));
        $this->renderLayout();
        \Maho\Profiler::stop(__METHOD__ . 'cart_display');
    }

    /**
     * Add product to shopping cart action
     *
     * @throws Mage_Core_Exception
     */
    public function addAction(): void
    {
        $isAjax = (bool) $this->getRequest()->getParam('isAjax');

        if (!$this->_validateFormKey()) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $this->__('Invalid form key. Please refresh the page.'),
                ]);
                return;
            }
            $this->_goBack();
            return;
        }

        $cart   = $this->_getCart();
        $params = $this->getRequest()->getParams();

        try {
            if (isset($params['qty'])) {
                $params['qty'] = Mage::app()->getLocale()->normalizeNumber($params['qty']);
            }

            $product = $this->_initProduct();
            $related = $this->getRequest()->getParam('related_product');

            /**
             * Check product availability
             */
            if (!$product) {
                if ($isAjax) {
                    $this->getResponse()->setBodyJson([
                        'success' => false,
                        'error' => $this->__('Product not found.'),
                    ]);
                    return;
                }
                $this->_goBack();
                return;
            }

            $cart->addProduct($product, $params);
            if (!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }

            $cart->save();

            $this->_getSession()->setCartWasUpdated(true);

            /**
             * @todo remove wishlist observer processAddToCart
             */
            Mage::dispatchEvent(
                'checkout_cart_add_product_complete',
                ['product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse()],
            );

            if ($isAjax) {
                $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));

                $this->loadLayout();
                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'message' => $message,
                    'qty' => $this->_getCart()->getSummaryQty(),
                    'content' => $this->getLayout()->getBlock('minicart_content')->toHtml(),
                ]);
                return;
            }

            if (!$this->_getSession()->getNoCartRedirect(true)) {
                if (!$cart->getQuote()->getHasError()) {
                    $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));
                    $this->_getSession()->addSuccess($message);
                }
                $this->_goBack();
            }
        } catch (Mage_Core_Exception $e) {
            if ($this->getRequest()->getParam('isAjax')) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
                return;
            }

            if ($this->_getSession()->getUseNotice(true)) {
                $this->_getSession()->addNotice(Mage::helper('core')->escapeHtml($e->getMessage()));
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->_getSession()->addError(Mage::helper('core')->escapeHtml($message, ['em']));
                }
            }

            $url = $this->_getSession()->getRedirectUrl(true);
            if ($url) {
                $this->_setProductBuyRequest();
                $this->getResponse()->setRedirect($url);
            } else {
                $this->_redirectReferer(Mage::helper('checkout/cart')->getCartUrl());
            }
        } catch (Exception $e) {
            if ($this->getRequest()->getParam('isAjax')) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $this->__('Cannot add the item to shopping cart.'),
                ]);
                return;
            }

            $this->_setProductBuyRequest();
            $this->_getSession()->addException($e, $this->__('Cannot add the item to shopping cart.'));
            $this->_goBack();
        }
    }

    /**
     * Add products in group to shopping cart action
     */
    public function addgroupAction(): void
    {
        $orderItemIds = $this->getRequest()->getParam('order_items', []);
        $customerId   = $this->_getCustomerSession()->getCustomerId();

        if (!is_array($orderItemIds) || !$this->_validateFormKey() || !$customerId) {
            $this->_goBack();
            return;
        }

        $itemsCollection = Mage::getModel('sales/order_item')
            ->getCollection()
            ->addFilterByCustomerId($customerId)
            ->addIdFilter($orderItemIds)
            ->load();
        /** @var Mage_Sales_Model_Resource_Order_Item_Collection $itemsCollection */
        $cart = $this->_getCart();
        foreach ($itemsCollection as $item) {
            try {
                $cart->addOrderItem($item, 1);
            } catch (Mage_Core_Exception $e) {
                if ($this->_getSession()->getUseNotice(true)) {
                    $this->_getSession()->addNotice($e->getMessage());
                } else {
                    $this->_getSession()->addError($e->getMessage());
                }
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Cannot add the item to shopping cart.'));
                $this->_goBack();
            }
        }
        $cart->save();
        $this->_getSession()->setCartWasUpdated(true);
        $this->_goBack();
    }

    /**
     * Action to reconfigure cart item
     */
    public function configureAction(): void
    {
        // Extract item and product to configure
        $id = (int) $this->getRequest()->getParam('id');
        $quoteItem = null;
        $cart = $this->_getCart();
        if ($id) {
            $quoteItem = $cart->getQuote()->getItemById($id);
        }

        if (!$quoteItem) {
            $this->_getSession()->addError($this->__('Quote item is not found.'));
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $params = new \Maho\DataObject();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);
            $params->setBuyRequest($quoteItem->getBuyRequest());

            Mage::helper('catalog/product_view')->prepareAndRender($quoteItem->getProduct()->getId(), $this, $params);
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Cannot configure product.'));
            Mage::logException($e);
            $this->_goBack();
            return;
        }
    }

    /**
     * Update product configuration for a cart item
     */
    public function updateItemOptionsAction(): void
    {
        $isAjax = (bool) $this->getRequest()->getParam('isAjax');

        if (!$this->_validateFormKey()) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $this->__('Invalid form key. Please refresh the page.'),
                ]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        }

        $cart   = $this->_getCart();
        $id = (int) $this->getRequest()->getParam('id');
        $params = $this->getRequest()->getParams();

        if (!isset($params['options'])) {
            $params['options'] = [];
        }
        try {
            if (isset($params['qty'])) {
                $params['qty'] = Mage::app()->getLocale()->normalizeNumber($params['qty']);
            }

            $quoteItem = $cart->getQuote()->getItemById($id);
            if (!$quoteItem) {
                Mage::throwException($this->__('Quote item is not found.'));
            }

            $item = $cart->updateItem($id, new \Maho\DataObject($params));
            if (is_string($item)) {
                Mage::throwException($item);
            }
            if ($item->getHasError()) {
                Mage::throwException($item->getMessage());
            }

            $related = $this->getRequest()->getParam('related_product');
            if (!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }

            $cart->save();

            $this->_getSession()->setCartWasUpdated(true);

            Mage::dispatchEvent(
                'checkout_cart_update_item_complete',
                ['item' => $item, 'request' => $this->getRequest(), 'response' => $this->getResponse()],
            );

            if ($isAjax) {
                $message = $this->__('%s was updated in your shopping cart.', Mage::helper('core')->escapeHtml($item->getProduct()->getName()));

                $this->loadLayout();
                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'message' => $message,
                    'qty' => $this->_getCart()->getSummaryQty(),
                    'content' => $this->getLayout()->getBlock('minicart_content')->toHtml(),
                ]);
                return;
            }

            if (!$this->_getSession()->getNoCartRedirect(true)) {
                if (!$cart->getQuote()->getHasError()) {
                    $message = $this->__('%s was updated in your shopping cart.', Mage::helper('core')->escapeHtml($item->getProduct()->getName()));
                    $this->_getSession()->addSuccess($message);
                }
                $this->_goBack();
            }
        } catch (Mage_Core_Exception $e) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
                return;
            }

            if ($this->_getSession()->getUseNotice(true)) {
                $this->_getSession()->addNotice($e->getMessage());
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->_getSession()->addError($message);
                }
            }

            $url = $this->_getSession()->getRedirectUrl(true);
            if ($url) {
                $this->getResponse()->setRedirect($url);
            } else {
                $this->_redirectReferer(Mage::helper('checkout/cart')->getCartUrl());
            }
        } catch (Exception $e) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => $this->__('Cannot update the item.'),
                ]);
                return;
            }

            $this->_setProductBuyRequest();
            $this->_getSession()->addException($e, $this->__('Cannot update the item.'));
            $this->_goBack();
        }
        $this->_redirect('*/*');
    }

    /**
     * Update shopping cart data action
     */
    public function updatePostAction(): void
    {
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*/');
            return;
        }

        $updateAction = (string) $this->getRequest()->getParam('update_cart_action');

        match ($updateAction) {
            'empty_cart' => $this->_emptyShoppingCart(),
            'update_qty' => $this->_updateShoppingCart(),
            default => $this->_updateShoppingCart(),
        };

        $this->_goBack();
    }

    /**
     * Update customer's shopping cart
     */
    protected function _updateShoppingCart()
    {
        try {
            $cartData = $this->getRequest()->getParam('cart');
            if (is_array($cartData)) {
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {
                        $cartData[$index]['qty'] = Mage::app()->getLocale()->normalizeNumber(trim($data['qty']));
                    }
                }
                $cart = $this->_getCart();
                if (!$cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }

                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)
                    ->save();
            }
            $this->_getSession()->setCartWasUpdated(true);
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Cannot update shopping cart.'));
        }
    }

    /**
     * Empty customer's shopping cart
     */
    protected function _emptyShoppingCart()
    {
        try {
            $this->_getCart()->truncate()->save();
            $this->_getSession()->setCartWasUpdated(true);
        } catch (Mage_Core_Exception $exception) {
            $this->_getSession()->addError($exception->getMessage());
        } catch (Exception $exception) {
            $this->_getSession()->addException($exception, $this->__('Cannot update shopping cart.'));
        }
    }

    /**
     * Delete shoping cart item action
     */
    public function deleteAction(): void
    {
        if ($this->_validateFormKey()) {
            $id = (int) $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $this->_getCart()->removeItem($id)
                        ->save();
                } catch (Exception $e) {
                    $this->_getSession()->addError($this->__('Cannot remove the item.'));
                    Mage::logException($e);
                }
            }
        } else {
            $this->_getSession()->addError($this->__('Cannot remove the item.'));
        }

        $this->_redirectReferer(Mage::getUrl('*/*'));
    }

    /**
     * Initialize shipping information
     */
    public function estimatePostAction(): void
    {
        $country    = (string) $this->getRequest()->getParam('country_id');
        $postcode   = (string) $this->getRequest()->getParam('estimate_postcode');
        $city       = (string) $this->getRequest()->getParam('estimate_city');
        $regionId   = (string) $this->getRequest()->getParam('region_id');
        $region     = (string) $this->getRequest()->getParam('region');
        $isAjax     = (bool) $this->getRequest()->getParam('isAjax');

        try {
            Mage::getModel('directory/country')->loadByCode($country);
        } catch (Mage_Core_Exception $e) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage(),
                ]);
                return;
            }
            $this->_getSession()->addError($e->getMessage());
            $this->_goBack();
            return;
        }

        $this->_getQuote()->getShippingAddress()
            ->setCountryId($country)
            ->setCity($city)
            ->setPostcode($postcode)
            ->setRegionId($regionId)
            ->setRegion($region)
            ->setCollectShippingRates(true)
            ->collectShippingRates();
        $this->_getQuote()->save();
        $this->_getSession()->setEstimatedShippingAddressData([
            'country_id' => $country,
            'postcode'   => $postcode,
            'city'       => $city,
            'region_id'  => $regionId,
            'region'     => $region,
        ]);

        if ($isAjax) {
            /** @var Mage_Checkout_Block_Cart_Shipping $block */
            $block = $this->getLayout()->createBlock('checkout/cart_shipping')
                ->setTemplate('checkout/cart/shipping/rates.phtml');

            $this->getResponse()->setBodyJson([
                'success' => true,
                'rates_html' => $block->toHtml(),
            ]);
            return;
        }

        $this->_goBack();
    }

    /**
     * Estimate update action
     */
    public function estimateUpdatePostAction(): void
    {
        $code = (string) $this->getRequest()->getParam('estimate_method');
        $isAjax = (bool) $this->getRequest()->getParam('isAjax');

        if (!empty($code)) {
            $this->_getQuote()->getShippingAddress()->setShippingMethod($code)->save();
            $this->_getQuote()->collectTotals()->save();
        }

        if ($isAjax) {
            $this->loadLayout('checkout_cart_index');
            $this->getResponse()->setBodyJson([
                'success' => true,
                'totals_html' => $this->getLayout()->getBlock('checkout.cart.totals')->toHtml(),
            ]);
            return;
        }

        $this->_goBack();
    }

    /**
     * Initialize coupon
     */
    public function couponPostAction(): void
    {
        $isAjax = (bool) $this->getRequest()->getParam('isAjax');

        /**
         * No reason continue with empty shopping cart
         */
        if (!$this->_getCart()->getQuote()->getItemsCount()) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'message' => $this->__('Your shopping cart is empty.'),
                ]);
                return;
            }
            $this->_goBack();
            return;
        }

        $couponCode = (string) $this->getRequest()->getParam('coupon_code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }
        $oldCouponCode = $this->_getQuote()->getCouponCode();

        if (!strlen($couponCode) && !strlen($oldCouponCode)) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson(['success' => true, 'message' => '']);
                return;
            }
            $this->_goBack();
            return;
        }

        try {
            $codeLength = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;

            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();

            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    $message = $this->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($couponCode));
                    if ($isAjax) {
                        $this->getResponse()->setBodyJson([
                            'success' => true,
                            'message' => $message,
                            'coupon_code' => $couponCode,
                        ]);
                        return;
                    }
                    $this->_getSession()->addSuccess($message);
                    $this->_getSession()->setCartCouponCode($couponCode);
                } else {
                    $message = $this->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode));
                    if ($isAjax) {
                        $this->getResponse()->setBodyJson([
                            'success' => false,
                            'message' => $message,
                        ]);
                        return;
                    }
                    $this->_getSession()->addError($message);
                }
            } else {
                $message = $this->__('Coupon code was canceled.');
                if ($isAjax) {
                    $this->getResponse()->setBodyJson([
                        'success' => true,
                        'message' => $message,
                        'coupon_code' => '',
                    ]);
                    return;
                }
                $this->_getSession()->setCartCouponCode('');
                $this->_getSession()->addSuccess($message);
            }
        } catch (Mage_Core_Exception $e) {
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
                return;
            }
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $message = $this->__('Cannot apply the coupon code.');
            if ($isAjax) {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'message' => $message,
                ]);
                Mage::logException($e);
                return;
            }
            $this->_getSession()->addError($message);
            Mage::logException($e);
        }

        $this->_goBack();
    }

    /**
     * Minicart delete action
     */
    public function ajaxDeleteAction(): void
    {
        if (!$this->_validateFormKey()) {
            Mage::throwException('Invalid form key');
        }
        $id = (int) $this->getRequest()->getParam('id');
        $result = [];
        if ($id) {
            try {
                $this->_getCart()->removeItem($id)->save();

                $result['qty'] = $this->_getCart()->getSummaryQty();

                $this->loadLayout();
                $result['content'] = $this->getLayout()->getBlock('minicart_content')->toHtml();

                $result['success'] = 1;
                $result['message'] = $this->__('Item was removed successfully.');
                Mage::dispatchEvent('ajax_cart_remove_item_success', ['id' => $id]);
            } catch (Exception $e) {
                $result['success'] = 0;
                $result['error'] = $this->__('Can not remove the item.');
            }
        }

        $this->getResponse()->setBodyJson($result);
    }

    /**
     * Minicart ajax update qty action
     */
    public function ajaxUpdateAction(): void
    {
        if (!$this->_validateFormKey()) {
            Mage::throwException('Invalid form key');
        }
        $id = (int) $this->getRequest()->getParam('id');
        $qty = $this->getRequest()->getParam('qty');
        $result = [];
        if ($id) {
            try {
                $cart = $this->_getCart();
                if (isset($qty)) {
                    $qty = Mage::app()->getLocale()->normalizeNumber($qty);
                }

                $quoteItem = $cart->getQuote()->getItemById($id);
                if (!$quoteItem) {
                    Mage::throwException($this->__('Quote item is not found.'));
                }
                if (is_numeric($qty) && $qty == 0) {
                    $cart->removeItem($id);
                } else {
                    $quoteItem->setQty($qty);
                }
                $this->_getCart()->save();

                $this->loadLayout();
                $result['content'] = $this->getLayout()->getBlock('minicart_content')->toHtml();

                $result['qty'] = $this->_getCart()->getSummaryQty();

                if (!$quoteItem->getHasError()) {
                    $result['message'] = $this->__('Item was updated successfully.');
                } else {
                    $result['notice'] = $quoteItem->getMessage();
                }
                $result['success'] = 1;
            } catch (Exception $e) {
                $result['success'] = 0;
                $result['error'] = $this->__('Can not save item.');
            }
        }

        $this->getResponse()->setBodyJson($result);
    }

    /**
     * Get customer session model
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Set product form data in checkout session for populating the product form
     * in case of errors in add to cart process.
     */
    protected function _setProductBuyRequest(): void
    {
        $buyRequest = $this->getRequest()->getPost();
        $buyRequestObject = new \Maho\DataObject($buyRequest);
        $this->_getSession()->setProductBuyRequest($buyRequestObject);
    }
}
