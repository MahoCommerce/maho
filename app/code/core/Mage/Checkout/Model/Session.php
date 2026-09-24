<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @method $this unsLastOrderId()
 * @method $this unsLastQuoteId()
 * @method $this unsLastRealOrderId()
 * @method $this unsLastSuccessQuoteId()
 *
 * @method $this unsRememberMeChecked()
 */
class Mage_Checkout_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public const CHECKOUT_STATE_BEGIN = 'begin';

    /**
     * Quote instance
     *
     * @var null|Mage_Sales_Model_Quote
     */
    protected $_quote;

    /**
     * Customer instance
     *
     * @var null|Mage_Customer_Model_Customer
     */
    protected $_customer;

    /**
     * Whether load only active quote
     *
     * @var bool
     */
    protected $_loadInactive = false;

    /**
     * Loaded order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Class constructor. Initialize checkout session namespace
     */
    public function __construct()
    {
        $this->init('checkout');
    }

    /**
     * Unset all data associated with object
     */
    #[\Override]
    public function unsetAll(): self
    {
        parent::unsetAll();
        $this->_quote = null;
        return $this;
    }

    /**
     * Set customer instance
     *
     * @param Mage_Customer_Model_Customer|null $customer
     * @return $this
     */
    public function setCustomer($customer)
    {
        $this->_customer = $customer;
        return $this;
    }

    /**
     * Check whether current session has quote
     *
     * @return bool
     */
    public function hasQuote()
    {
        return isset($this->_quote);
    }

    /**
     * Set quote to be loaded even if inactive
     *
     * @param bool $load
     * @return $this
     */
    public function setLoadInactive($load = true)
    {
        $this->_loadInactive = $load;
        return $this;
    }

    /**
     * Get checkout quote instance by current session
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        Mage::dispatchEvent('custom_quote_process', ['checkout_session' => $this]);

        if ($this->_quote === null) {
            /** @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore()->getId());
            if ($this->getQuoteId()) {
                if ($this->_loadInactive) {
                    $quote->load($this->getQuoteId());
                } else {
                    $quote->loadActive($this->getQuoteId());
                }
                if ($quote->getId()) {
                    /**
                     * If current currency code of quote is not equal current currency code of store,
                     * need recalculate totals of quote. It is possible if customer use currency switcher or
                     * store switcher.
                     */
                    if ($quote->getQuoteCurrencyCode() != Mage::app()->getStore()->getCurrentCurrencyCode()) {
                        $quote->setStore(Mage::app()->getStore());
                        $quote->collectTotals()->save();
                        /*
                         * We mast to create new quote object, because collectTotals()
                         * can to create links with other objects.
                         */
                        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore()->getId());
                        $quote->load($this->getQuoteId());
                    }
                } else {
                    $this->setQuoteId(null);
                }
            }

            $customerSession = Mage::getSingleton('customer/session');

            if (!$this->getQuoteId()) {
                if ($customerSession->isLoggedIn() || $this->_customer) {
                    $customer = $this->_customer ?: $customerSession->getCustomer();
                    $quote->loadByCustomer($customer);
                    $this->setQuoteId($quote->getId());
                } else {
                    $quote->setIsCheckoutCart();
                    Mage::dispatchEvent('checkout_quote_init', ['quote' => $quote]);
                }
            }

            if ($this->getQuoteId()) {
                if ($customerSession->isLoggedIn() || $this->_customer) {
                    $customer = $this->_customer ?: $customerSession->getCustomer();
                    $quote->setCustomer($customer);
                }
            }

            $quote->setStore(Mage::app()->getStore());
            $this->_quote = $quote;
        }

        if ($remoteAddr = Mage::helper('core/http')->getRemoteAddr()) {
            $this->_quote->setRemoteIp($remoteAddr);
            $xForwardIp = Mage::app()->getRequest()->getServer('HTTP_X_FORWARDED_FOR');
            $this->_quote->setXForwardedFor($xForwardIp);
        }
        return $this->_quote;
    }

    /**
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getQuoteIdKey()
    {
        return 'quote_id_' . Mage::app()->getStore()->getWebsiteId();
    }

    /**
     * @param int|null $quoteId
     */
    public function setQuoteId($quoteId)
    {
        $this->setData($this->_getQuoteIdKey(), $quoteId);
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData($this->_getQuoteIdKey());
    }

    /**
     * Load data for customer quote and merge with current quote
     *
     * @return $this
     */
    public function loadCustomerQuote()
    {
        if (!Mage::getSingleton('customer/session')->getCustomerId()) {
            return $this;
        }

        Mage::dispatchEvent('load_customer_quote_before', ['checkout_session' => $this]);

        $customerQuote = Mage::getModel('sales/quote')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->loadByCustomer(Mage::getSingleton('customer/session')->getCustomerId());

        if ($customerQuote->getId() && $this->getQuoteId() != $customerQuote->getId()) {
            if ($this->getQuoteId()) {
                $customerQuote->merge($this->getQuote())
                    ->collectTotals()
                    ->save();
            }

            $this->setQuoteId($customerQuote->getId());

            if ($this->_quote) {
                $this->_quote->delete();
            }
            $this->_quote = $customerQuote;
        } else {
            $this->getQuote()->getBillingAddress();
            $this->getQuote()->getShippingAddress();
            $this->getQuote()->setCustomer(Mage::getSingleton('customer/session')->getCustomer())
                ->setTotalsCollectedFlag(false)
                ->collectTotals()
                ->save();
        }
        return $this;
    }

    /**
     * Set step data for given checkout step (e.g. "billing").
     * By providing the two parameters data and value, the data will be added to existing step data.
     * By providing an associative array [data => value, ...] the existing step data will be replaced.
     *
     * @param string $step
     * @param array|string $data
     * @param mixed|null $value
     * @return $this
     */
    public function setStepData($step, $data, $value = null)
    {
        $steps = $this->getSteps();
        if (is_null($value)) {
            if (is_array($data)) {
                $steps[$step] = $data;
            }
        } else {
            $steps[$step] ??= [];
            if (is_string($data)) {
                $steps[$step][$data] = $value;
            }
        }
        $this->setSteps($steps);

        return $this;
    }

    /**
     * Returns existing step data for all steps ($step = null) or the provided checkout step.
     * By providing $data only this data of the given step will be returned, or false if not set.
     *
     * @param string|null $step
     * @param string|null $data
     * @return array|mixed|false
     */
    public function getStepData($step = null, $data = null)
    {
        $steps = $this->getSteps();
        if (is_null($step)) {
            return $steps;
        }
        if (!isset($steps[$step])) {
            return false;
        }
        if (is_null($data)) {
            return $steps[$step];
        }
        if (!is_string($data) || !isset($steps[$step][$data])) {
            return false;
        }
        return $steps[$step][$data];
    }

    /**
     * Retrieves list of all saved additional messages for different instances (e.g. quote items) in checkout session
     * Returned: [itemKey => messageCollection, ...]
     * where itemKey is a unique hash (e.g 'quote_item17') to distinguish item messages among message collections
     *
     * @param bool $clear
     *
     * @return array
     */
    public function getAdditionalMessages($clear = false)
    {
        $additionalMessages = $this->getData('additional_messages');
        if (!$additionalMessages) {
            return [];
        }
        if ($clear) {
            $this->setData('additional_messages');
        }
        return $additionalMessages;
    }

    /**
     * Retrieves list of item additional messages
     * itemKey is a unique hash (e.g 'quote_item17') to distinguish item messages among message collections
     *
     * @param string $itemKey
     * @param bool $clear
     *
     * @return null|Mage_Core_Model_Message_Collection
     */
    public function getItemAdditionalMessages($itemKey, $clear = false)
    {
        $allMessages = $this->getAdditionalMessages();
        if (!isset($allMessages[$itemKey])) {
            return null;
        }

        $messages = $allMessages[$itemKey];
        if ($clear) {
            unset($allMessages[$itemKey]);
            $this->setAdditionalMessages($allMessages);
        }
        return $messages;
    }

    /**
     * Adds new message in this session to a list of additional messages for some item
     * itemKey is a unique hash (e.g 'quote_item17') to distinguish item messages among message collections
     *
     * @param string $itemKey
     * @param Mage_Core_Model_Message_Abstract $message
     *
     * @return $this
     */
    public function addItemAdditionalMessage($itemKey, $message)
    {
        $allMessages = $this->getAdditionalMessages();
        $allMessages[$itemKey] ??= Mage::getModel('core/message_collection');
        $allMessages[$itemKey]->add($message);
        $this->setAdditionalMessages($allMessages);

        return $this;
    }

    /**
     * Retrieves list of quote item messages
     * @param int $itemId
     * @param bool $clear
     *
     * @return null|Mage_Core_Model_Message_Collection
     */
    public function getQuoteItemMessages($itemId, $clear = false)
    {
        return $this->getItemAdditionalMessages('quote_item' . $itemId, $clear);
    }

    /**
     * Adds new message to a list of quote item messages, saved in this session
     *
     * @param int $itemId
     * @param Mage_Core_Model_Message_Abstract $message
     *
     * @return $this
     */
    public function addQuoteItemMessage($itemId, $message)
    {
        return $this->addItemAdditionalMessage('quote_item' . $itemId, $message);
    }

    #[\Override]
    public function clear(): self
    {
        Mage::dispatchEvent('checkout_quote_destroy', ['quote' => $this->getQuote()]);
        $this->_quote = null;
        $this->setQuoteId(null);
        $this->setLastSuccessQuoteId(null);
        return $this;
    }

    /**
     * Clear misc checkout parameters
     */
    public function clearHelperData()
    {
        $this->setLastBillingAgreementId(null)
            ->setRedirectUrl(null)
            ->setLastOrderId(null)
            ->setLastRealOrderId(null)
            ->setLastRecurringProfileIds(null)
            ->setAdditionalMessages(null)
        ;
    }

    /**
     * @return $this
     */
    public function resetCheckout()
    {
        $this->setCheckoutState(self::CHECKOUT_STATE_BEGIN);
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return $this
     */
    public function replaceQuote($quote)
    {
        $this->_quote = $quote;
        $this->setQuoteId($quote->getId());
        return $this;
    }

    /**
     * Get order instance based on last order ID
     *
     * @return Mage_Sales_Model_Order
     */
    public function getLastRealOrder()
    {
        $orderId = $this->getLastRealOrderId();
        if ($this->_order !== null && $orderId == $this->_order->getIncrementId()) {
            return $this->_order;
        }
        $this->_order = $this->_getOrderModel();
        if ($orderId) {
            $this->_order->loadByIncrementId($orderId);
        }
        return $this->_order;
    }

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrderModel()
    {
        return Mage::getModel('sales/order');
    }

    public function setAdditionalMessages(?array $value): static
    {
        return $this->setData('additional_messages', $value);
    }

    public function getCartCouponCode(bool $clear = false): ?string
    {
        $value = $this->getData('cart_coupon_code', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setCartCouponCode(?string $value): static
    {
        return $this->setData('cart_coupon_code', $value);
    }

    public function getCartWasUpdated(bool $clear = false): ?bool
    {
        $value = $this->getData('cart_was_updated', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setCartWasUpdated(?bool $value = true): static
    {
        return $this->setData('cart_was_updated', $value);
    }

    public function getCheckoutState(bool $clear = false): ?string
    {
        $value = $this->getData('checkout_state', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setCheckoutState(?string $value): static
    {
        return $this->setData('checkout_state', $value);
    }

    public function getContinueShoppingUrl(bool $clear = false): ?string
    {
        $value = $this->getData('continue_shopping_url', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setContinueShoppingUrl(?string $value): static
    {
        return $this->setData('continue_shopping_url', $value);
    }

    public function getDisplaySuccess(bool $clear = false): ?bool
    {
        $value = $this->getData('display_success', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setDisplaySuccess(?bool $value = true): static
    {
        return $this->setData('display_success', $value);
    }

    public function getEstimatedShippingAddressData(bool $clear = false): ?array
    {
        return $this->getData('estimated_shipping_address_data', $clear ?: null);
    }

    public function setEstimatedShippingAddressData(?array $value): static
    {
        return $this->setData('estimated_shipping_address_data', $value);
    }

    public function getGotoSection(bool $clear = false): ?string
    {
        $value = $this->getData('goto_section', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setGotoSection(?string $value): static
    {
        return $this->setData('goto_section', $value);
    }

    public function getHasDownloadableProducts(bool $clear = false): ?bool
    {
        $value = $this->getData('has_downloadable_products', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setHasDownloadableProducts(?bool $value = true): static
    {
        return $this->setData('has_downloadable_products', $value);
    }

    public function getLastAddedProductId(bool $clear = false): ?int
    {
        $value = $this->getData('last_added_product_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setLastAddedProductId(?int $value): static
    {
        return $this->setData('last_added_product_id', $value);
    }

    public function getLastBillingAgreementId(bool $clear = false): ?int
    {
        $value = $this->getData('last_billing_agreement_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setLastBillingAgreementId(?int $value): static
    {
        return $this->setData('last_billing_agreement_id', $value);
    }

    public function getLastOrderId(bool $clear = false): ?int
    {
        $value = $this->getData('last_order_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setLastOrderId(?int $value): static
    {
        return $this->setData('last_order_id', $value);
    }

    public function getLastQuoteId(bool $clear = false): ?int
    {
        $value = $this->getData('last_quote_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setLastQuoteId(?int $value): static
    {
        return $this->setData('last_quote_id', $value);
    }

    public function getLastRealOrderId(bool $clear = false): ?string
    {
        $value = $this->getData('last_real_order_id', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setLastRealOrderId(?string $value): static
    {
        return $this->setData('last_real_order_id', $value);
    }

    public function getLastRecurringProfileIds(bool $clear = false): ?array
    {
        return $this->getData('last_recurring_profile_ids', $clear ?: null);
    }

    public function setLastRecurringProfileIds(?array $value): static
    {
        return $this->setData('last_recurring_profile_ids', $value);
    }

    public function getLastSuccessQuoteId(bool $clear = false): ?int
    {
        $value = $this->getData('last_success_quote_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setLastSuccessQuoteId(?int $value): static
    {
        return $this->setData('last_success_quote_id', $value);
    }

    public function getMethodData(bool $clear = false): ?array
    {
        return $this->getData('method_data', $clear ?: null);
    }

    public function getNoCartRedirect(bool $clear = false): ?bool
    {
        $value = $this->getData('no_cart_redirect', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setNoCartRedirect(?bool $value = true): static
    {
        return $this->setData('no_cart_redirect', $value);
    }

    public function getPaypalTransactionData(bool $clear = false): ?array
    {
        return $this->getData('paypal_transaction_data', $clear ?: null);
    }

    public function getRedirectUrl(bool $clear = false): ?string
    {
        $value = $this->getData('redirect_url', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setRedirectUrl(?string $value): static
    {
        return $this->setData('redirect_url', $value);
    }

    public function getRememberMeChecked(bool $clear = false): ?bool
    {
        $value = $this->getData('remember_me_checked', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setRememberMeChecked(?bool $value = true): static
    {
        return $this->setData('remember_me_checked', $value);
    }

    public function getSharedWishlist(bool $clear = false): ?string
    {
        $value = $this->getData('shared_wishlist', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setSharedWishlist(?string $value): static
    {
        return $this->setData('shared_wishlist', $value);
    }

    public function getSingleWishlistId(bool $clear = false): ?int
    {
        $value = $this->getData('single_wishlist_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setSingleWishlistId(?int $value): static
    {
        return $this->setData('single_wishlist_id', $value);
    }

    public function getSteps(bool $clear = false): ?array
    {
        return $this->getData('steps', $clear ?: null);
    }

    public function setSteps(?array $value): static
    {
        return $this->setData('steps', $value);
    }

    public function getUpdateSection(bool $clear = false): ?string
    {
        $value = $this->getData('update_section', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setUpdateSection(?string $value): static
    {
        return $this->setData('update_section', $value);
    }

    public function getUseNotice(bool $clear = false): ?bool
    {
        $value = $this->getData('use_notice', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setUseNotice(?bool $value = true): static
    {
        return $this->setData('use_notice', $value);
    }

    public function getWishlistIds(bool $clear = false): ?array
    {
        return $this->getData('wishlist_ids', $clear ?: null);
    }

    public function setWishlistIds(?array $value): static
    {
        return $this->setData('wishlist_ids', $value);
    }

    public function getWishlistPendingMessages(bool $clear = false): ?array
    {
        return $this->getData('wishlist_pending_messages', $clear ?: null);
    }

    public function setWishlistPendingMessages(?array $value): static
    {
        return $this->setData('wishlist_pending_messages', $value);
    }

    public function getWishlistPendingUrls(bool $clear = false): ?array
    {
        return $this->getData('wishlist_pending_urls', $clear ?: null);
    }

    public function setWishlistPendingUrls(?array $value): static
    {
        return $this->setData('wishlist_pending_urls', $value);
    }

}
