<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setAdditionalMessages(array|null $value)
 *
 * @method string getCartCouponCode()
 * @method $this setCartCouponCode(string $value)
 * @method bool getCartWasUpdated()
 * @method $this setCartWasUpdated(bool $value)
 * @method string getCheckoutState()
 * @method $this setCheckoutState(string $value)
 * @method string getContinueShoppingUrl()
 * @method $this setContinueShoppingUrl(string $value)
 *
 * @method bool getDisplaySuccess()
 * @method $this setDisplaySuccess(bool $value)
 *
 * @method array getEstimatedShippingAddressData()
 * @method $this setEstimatedShippingAddressData(array $value)
 *
 * @method bool getHasDownloadableProducts()
 *
 * @method string getGotoSection()
 * @method $this setGotoSection(string $value)
 *
 * @method $this setHasDownloadableProducts(bool $value)
 *
 * @method int getLastAddedProductId()
 * @method $this setLastAddedProductId(int $value)
 * @method int getLastBillingAgreementId()
 * @method $this setLastBillingAgreementId(int|null $value)
 * @method int getLastOrderId()
 * @method $this setLastOrderId(int|null $value)
 * @method $this unsLastOrderId()
 * @method int getLastQuoteId()
 * @method $this setLastQuoteId(int $value)
 * @method $this unsLastQuoteId()
 * @method string getLastRealOrderId()
 * @method $this setLastRealOrderId(string $value)
 * @method $this unsLastRealOrderId()
 * @method int getLastRecurringProfileIds()
 * @method $this setLastRecurringProfileIds(array|null $value)
 * @method int getLastSuccessQuoteId()
 * @method $this setLastSuccessQuoteId(int|null $value)
 * @method $this unsLastSuccessQuoteId()
 *
 * @method array getMethodData()
 *
 * @method bool getNoCartRedirect()
 * @method $this setNoCartRedirect(bool $value)
 *
 * @method array getPaypalTransactionData()
 *
 * @method string getRedirectUrl()
 * @method $this setRedirectUrl(string $value)
 * @method bool getRememberMeChecked()
 * @method $this setRememberMeChecked(bool $value)
 * @method $this unsRememberMeChecked()
 *
 * @method string getSharedWishlist()
 * @method $this setSharedWishlist(string $value)
 * @method int getSingleWishlistId()
 * @method $this setSingleWishlistId(int $value)
 * @method array getSteps()
 * @method $this setSteps(array $value)
 *
 * @method string getUpdateSection()
 * @method $this setUpdateSection(string $value)
 * @method bool getUseNotice()
 * @method $this setUseNotice(bool $value)
 *
 * @method array getWishlistIds()
 * @method $this setWishlistIds(array $value)
 * @method array getWishlistPendingMessages()
 * @method $this setWishlistPendingMessages(array $value)
 * @method array getWishlistPendingUrls()
 * @method $this setWishlistPendingUrls(array $value)
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
                    $quote->setIsCheckoutCart(true);
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
            if (!isset($steps[$step])) {
                $steps[$step] = [];
            }
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
     * Returned: array(itemKey => messageCollection, ...)
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
            $this->setData('additional_messages', null);
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
     * @param Mage_Core_Model_Message $message
     *
     * @return $this
     */
    public function addItemAdditionalMessage($itemKey, $message)
    {
        $allMessages = $this->getAdditionalMessages();
        if (!isset($allMessages[$itemKey])) {
            $allMessages[$itemKey] = Mage::getModel('core/message_collection');
        }
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
     * @param Mage_Core_Model_Message $message
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
}
