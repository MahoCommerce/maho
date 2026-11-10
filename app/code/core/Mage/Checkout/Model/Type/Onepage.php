<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Model_Type_Onepage
{
    /**
     * Checkout types: Checkout as Guest, Register, Logged In Customer
     */
    public const METHOD_GUEST    = 'guest';
    public const METHOD_REGISTER = 'register';
    public const METHOD_CUSTOMER = 'customer';

    /**
     * Error message of "customer already exists"
     *
     * @var string
     */
    private $_customerEmailExistsMessage = '';

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $_customerSession;

    /**
     * @var Mage_Checkout_Model_Session
     */
    protected $_checkoutSession;

    /**
     * @var Mage_Sales_Model_Quote|null
     */
    protected $_quote = null;

    /**
     * @var Mage_Checkout_Helper_Data
     */
    protected $_helper;

    /**
     * Class constructor
     * Set customer already exists message
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('checkout');
        $this->_customerEmailExistsMessage = Mage::helper('checkout')->__('There is already a customer registered using this email address. Please login using this email address or enter a different email address to register your account.');
        $this->_checkoutSession = Mage::getSingleton('checkout/session');
        $this->_customerSession = Mage::getSingleton('customer/session');
    }

    /**
     * Get frontend checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return $this->_checkoutSession;
    }

    /**
     * Quote object getter
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_quote ?? $this->_checkoutSession->getQuote();
    }

    /**
     * Declare checkout quote instance
     *
     * @return $this
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * Get customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * Initialize quote state to be valid for one page checkout
     *
     * @return $this
     */
    public function initCheckout()
    {
        $checkout = $this->getCheckout();
        $customerSession = $this->getCustomerSession();
        if (is_array($checkout->getStepData())) {
            foreach (array_keys($checkout->getStepData()) as $step) {
                // Only billing is allowed initially, other steps unlock as checkout progresses
                if ($step !== 'billing') {
                    $checkout->setStepData($step, 'allow', false);
                }
            }
        }

        /*
        * want to load the correct customer information by assigning to address
        * instead of just loading from sales/quote_address
        */
        $customer = $customerSession->getCustomer();
        if ($customer) {
            $this->getQuote()->assignCustomer($customer);

            // Single saved address used for both: default the shipping radio to
            // "same as billing". Render-scoped flag, the quote is not re-saved here.
            $defaultBilling = $customer->getDefaultBilling();
            if ($defaultBilling && $defaultBilling == $customer->getDefaultShipping()) {
                $this->getQuote()->getShippingAddress()->setSameAsBilling();
            }
        }
        return $this;
    }

    /**
     * Get quote checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        return Mage::helper('checkout')->getCheckoutMethod($this->getQuote());
    }

    /**
     * Specify checkout method
     *
     * @param   string $method
     * @return  array
     */
    public function saveCheckoutMethod($method)
    {
        if (empty($method)) {
            return ['error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data.')];
        }

        $this->getQuote()->setCheckoutMethod($method)->save();
        $this->getCheckout()->setStepData('billing', 'allow', true);
        return [];
    }

    /**
     * Get customer address by identifier
     *
     * @param   int $addressId
     * @return  Mage_Customer_Model_Address
     */
    public function getAddress($addressId)
    {
        $address = Mage::getModel('customer/address')->load((int) $addressId);
        $address->explodeStreetAddress();
        if ($address->getRegionId()) {
            $address->setRegion((string) $address->getRegionId());
        }
        return $address;
    }

    /**
     * Save billing address information to quote
     * This method is called by One Page Checkout JS (AJAX) while saving the billing information.
     *
     * @param array $data
     * @param int $customerAddressId
     * @return array|true
     * @throws Mage_Core_Exception
     */
    public function saveBilling($data, $customerAddressId)
    {
        if (empty($data)) {
            return ['error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data.')];
        }

        $quote = $this->getQuote();
        $address = $quote->getBillingAddress();
        try {
            if (!empty($customerAddressId)) {
                $this->_getCartService()->useCustomerAddress($quote, Mage_Sales_Model_Quote_Address::TYPE_BILLING, (int) $customerAddressId);
            } else {
                $this->_importAddressForm($address, $data);
                $address->setCustomerAddressId(null);
                // Additional form data, not fetched by extractData (as it fetches only attributes)
                $address->setSaveInAddressBook(!empty($data['save_in_address_book']));
                $this->_getCartService()->validateAddress($address);
            }
        } catch (Mage_Core_Exception_Input $e) {
            return ['error' => 1, 'message' => array_column($e->getErrors(), 'message')];
        } catch (Mage_Core_Exception $e) {
            return ['error' => 1, 'message' => $e->getMessage()];
        }

        // set email for newly created user
        if (!$address->getEmail() && $quote->getCustomerEmail()) {
            $address->setEmail($quote->getCustomerEmail());
        }

        if (($result = $this->_validateCustomerData($data)) !== true) {
            return $result;
        }

        if (!$quote->getCustomerId() && self::METHOD_REGISTER == $quote->getCheckoutMethod()) {
            if ($this->_customerEmailExists($address->getEmail(), Mage::app()->getWebsite()->getId())) {
                return ['error' => 1, 'message' => $this->_customerEmailExistsMessage];
            }
        }

        if (!$quote->isVirtual() && (int) ($data['use_for_shipping'] ?? 0) === 1) {
            $this->getCheckout()->setStepData('shipping', 'complete', true);
            $this->_setCartCouponCode();
            $this->_getCartService()->useBillingForShipping($quote);
        } else {
            if (!$quote->isVirtual()) {
                $quote->getShippingAddress()->setSameAsBilling(false);
            }
            $this->_getCartService()->collectAndSave($quote);
        }

        if (!$quote->isVirtual() && $this->getCheckout()->getStepData('shipping', 'complete') == true) {
            //Recollect Shipping rates for shipping methods
            $quote->getShippingAddress()->setCollectShippingRates();
        }

        $this->getCheckout()
            ->setStepData('billing', 'allow', true)
            ->setStepData('billing', 'complete', true)
            ->setStepData('shipping', 'allow', true);

        return [];
    }

    /**
     * Put the fields of the address form of $data into $address. A field of the form that $data does not hold is
     * emptied.
     */
    protected function _importAddressForm(Mage_Sales_Model_Quote_Address $address, array $data): void
    {
        /** @var Mage_Customer_Model_Form $addressForm */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
            ->setEntity($address)
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());
        // emulate request object
        $addressForm->compactData($addressForm->extractData($addressForm->prepareRequest($data)));
        foreach ($addressForm->getAttributes() as $attribute) {
            if (!isset($data[$attribute->getAttributeCode()])) {
                $address->setData($attribute->getAttributeCode());
            }
        }
    }

    protected function _getCartService(): Mage_Checkout_Service_Cart
    {
        return Mage::getService('checkout/cart');
    }

    /**
     * Validate customer data and set some its data for further usage in quote
     * Will return either true or array with error messages
     *
     * @return true|array
     */
    protected function _validateCustomerData(array $data)
    {
        $quote = $this->getQuote();
        // The billing form shows no customer fields to a logged-in customer, who could not fix an error
        if ($quote->getCustomerId()) {
            return true;
        }

        /** @var Mage_Customer_Model_Form $customerForm */
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('checkout_register')
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer');
        $customerForm->setEntity($customer);
        $customerRequest = $customerForm->prepareRequest($data);
        $customerData = $customerForm->extractData($customerRequest);

        $customerErrors = $customerForm->validateData($customerData);
        if ($customerErrors !== true) {
            return [
                'error'     => -1,
                'message'   => implode(', ', $customerErrors),
            ];
        }

        $customerForm->compactData($customerData);

        if ($quote->getCheckoutMethod() == self::METHOD_REGISTER) {
            // set customer password
            $customer->setPassword($customerRequest->getParam('customer_password'));
            $customer->setPasswordConfirmation($customerRequest->getParam('confirm_password'));
        } else {
            // spoof customer password for guest
            $password = $customer->generatePassword();
            $customer->setPassword($password);
            $customer->setPasswordConfirmation($password);
            // set NOT LOGGED IN group id explicitly,
            // otherwise copyFieldset('customer_account', 'to_quote') will fill it with default group id value
            $customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        }

        $result = $customer->validate();
        if ($result !== true && is_array($result)) {
            return [
                'error'   => -1,
                'message' => implode(', ', $result),
            ];
        }

        if ($quote->getCheckoutMethod() == self::METHOD_REGISTER) {
            // save customer encrypted password in quote
            $quote->setPasswordHash($customer->encryptPassword($customer->getPassword()));
        }

        // copy customer/guest email to address
        $quote->getBillingAddress()->setEmail($customer->getEmail());

        // copy customer data to quote
        Mage::helper('core')->copyFieldset('customer_account', 'to_quote', $customer, $quote);

        return true;
    }

    /**
     * Save checkout shipping address
     *
     * @param array $data
     * @param int $customerAddressId
     * @return array
     */
    public function saveShipping($data, $customerAddressId)
    {
        if (empty($data)) {
            return ['error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data.')];
        }
        $quote = $this->getQuote();
        $address = $quote->getShippingAddress();
        $this->_setCartCouponCode();

        try {
            if (!empty($customerAddressId)) {
                $this->_getCartService()->useCustomerAddress($quote, Mage_Sales_Model_Quote_Address::TYPE_SHIPPING, (int) $customerAddressId);
            } else {
                $this->_importAddressForm($address, $data);
                $address->setCustomerAddressId(null);
                // Additional form data, not fetched by extractData (as it fetches only attributes)
                $address->setSaveInAddressBook(!empty($data['save_in_address_book']));
                $address->setSameAsBilling(!empty($data['same_as_billing']));
                $address->setCollectShippingRates();
                $this->_getCartService()->validateAddress($address);
                $this->_getCartService()->collectAndSave($quote);
            }
        } catch (Mage_Core_Exception_Input $e) {
            return ['error' => 1, 'message' => array_column($e->getErrors(), 'message')];
        } catch (Mage_Core_Exception $e) {
            return ['error' => 1, 'message' => $e->getMessage()];
        }

        $this->getCheckout()
            ->setStepData('shipping', 'complete', true)
            ->setStepData('shipping_method', 'allow', true);

        return [];
    }

    /**
     * Specify quote shipping method
     *
     * @param   string $shippingMethod
     * @return  array
     */
    public function saveShippingMethod($shippingMethod)
    {
        if (empty($shippingMethod)) {
            return ['error' => -1, 'message' => Mage::helper('checkout')->__('Invalid shipping method.')];
        }
        try {
            $this->_getCartService()->selectShippingMethod($this->getQuote(), (string) $shippingMethod);
        } catch (Mage_Core_Exception $e) {
            return ['error' => -1, 'message' => $e->getMessage()];
        }

        $this->getCheckout()
            ->setStepData('shipping_method', 'complete', true)
            ->setStepData('payment', 'allow', true);

        return [];
    }

    /**
     * Specify quote payment method
     *
     * @param   array $data
     * @return  array
     * @throws  Mage_Core_Exception when the method is not available for the cart, or refuses the data
     */
    public function savePayment($data)
    {
        if (empty($data)) {
            return ['error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data.')];
        }
        $this->_getCartService()->selectPaymentMethod($this->getQuote(), (array) $data);

        $this->getCheckout()
            ->setStepData('payment', 'complete', true)
            ->setStepData('review', 'allow', true);

        return [];
    }

    /**
     * Validate quote state to be integrated with one page checkout process
     */
    public function validate()
    {
        $quote  = $this->getQuote();

        // A shopper who registers in the checkout gets a customer before the order is placed
        if ($quote->getCheckoutMethod() == self::METHOD_GUEST) {
            Mage::getService('sales/order')->assertGuestCheckoutAllowed($quote);
        }
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function _prepareGuestQuote()
    {
        $quote = $this->getQuote();
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest()
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Prepare quote for customer registration and customer order submit
     */
    protected function _prepareNewCustomerQuote()
    {
        $quote      = $this->getQuote();
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        //$customer = Mage::getModel('customer/customer');
        $customer = $quote->getCustomer();
        /** @var Mage_Customer_Model_Customer $customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling();
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping();
        } else {
            $customerBilling->setIsDefaultShipping();
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $quote->setCustomer($customer)
            ->setCustomerIsNew();
        $quote->setPasswordHash('');
    }

    /**
     * Prepare quote for customer order submit
     */
    protected function _prepareCustomerQuote()
    {
        $quote      = $this->getQuote();
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->getCustomerSession()->getCustomer();
        if (!$customer->getId() && $quote->getCustomerId()) {
            $customer = Mage::getModel('customer/customer')->load($quote->getCustomerId());
        }
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && !$shipping->getSameAsBilling()
            && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling();
        }
        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping();
        } elseif (isset($customerBilling) && !$customer->getDefaultShipping()) {
            $customerBilling->setIsDefaultShipping();
        }
        $quote->setCustomer($customer);
    }

    /**
     * Log in the customer who registered in the checkout, or tell the customer to confirm the email first.
     * Mage_Customer_Service_Customer::register() sends the email.
     *
     * @return $this
     */
    protected function _involveNewCustomer()
    {
        $customer = $this->getQuote()->getCustomer();
        if ($customer->isConfirmationRequired() && $customer->getConfirmation()) {
            $url = Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
            $this->getCustomerSession()->addSuccess(
                Mage::helper('customer')->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please %s.'),
                new \Maho\Message\Link(Mage::helper('customer')->__('click here'), $url),
            );
        } else {
            $this->getCustomerSession()
                 ->setRememberMe($this->_checkoutSession->getRememberMe())
                 ->loginById($customer->getId());
        }
        return $this;
    }

    /**
     * Create order based on checkout type. Create customer if necessary.
     *
     * @param list<int|string>|null $agreementIds the agreements that the customer accepted, or null when the flow
     *                                            of the caller has no agreements step
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function saveOrder(?array $agreementIds = null)
    {
        $this->validate();
        $isNewCustomer = false;
        switch ($this->getCheckoutMethod()) {
            case self::METHOD_GUEST:
                $this->_prepareGuestQuote();
                break;
            case self::METHOD_REGISTER:
                $this->_prepareNewCustomerQuote();
                $isNewCustomer = true;
                break;
            default:
                $this->_prepareCustomerQuote();
                break;
        }

        $quote = $this->getQuote();
        $order = null;
        $placeOrder = function () use ($quote, $agreementIds, &$order): void {
            $order = Mage::getService('sales/order')->submit($quote, Mage_Checkout_Model_Actor::Customer, $agreementIds);
        };
        if ($isNewCustomer) {
            /** @var Mage_Customer_Service_Customer $customerService */
            $customerService = Mage::getService('customer/customer');
            $customerService->register($quote->getCustomer(), (int) $quote->getStoreId(), '', $placeOrder);
            try {
                $this->_involveNewCustomer();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        } else {
            $placeOrder();
        }
        /** @var Mage_Sales_Model_Order $order */

        $this->_checkoutSession->setLastQuoteId($this->getQuote()->getId())
            ->setLastSuccessQuoteId($this->getQuote()->getId())
            ->clearHelperData();

        Mage::dispatchEvent(
            'checkout_type_onepage_save_order_after',
            ['order' => $order, 'quote' => $this->getQuote()],
        );

        // A payment method that redirects to a third party gives the URL, and the order email waits for its answer
        $this->_checkoutSession->setLastOrderId($order->getId())
            ->setRedirectUrl($this->getQuote()->getPayment()->getOrderPlaceRedirectUrl())
            ->setLastRealOrderId($order->getIncrementId());

        // as well a billing agreement can be created
        $agreement = $order->getPayment()->getBillingAgreement();
        if ($agreement) {
            $this->_checkoutSession->setLastBillingAgreementId($agreement->getId());
        }

        return $this;
    }

    /**
     * Check if customer email exists
     *
     * @param string $email
     * @param int $websiteId
     * @return false|Mage_Customer_Model_Customer
     */
    protected function _customerEmailExists(#[\SensitiveParameter] $email, $websiteId = null)
    {
        $customer = Mage::getModel('customer/customer');
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }
        $customer->loadByEmail($email);
        if ($customer->getId()) {
            return $customer;
        }
        return false;
    }

    /**
     * Get last order increment id by order id
     *
     * @return string
     */
    public function getLastOrderId()
    {
        $lastId  = $this->getCheckout()->getLastOrderId();
        $orderId = false;
        if ($lastId) {
            $order = Mage::getModel('sales/order');
            $order->load($lastId);
            $orderId = $order->getIncrementId();
        }
        return $orderId;
    }

    /**
     * Sets cart coupon code from checkout to quote
     *
     * @return $this
     */
    protected function _setCartCouponCode()
    {
        if ($couponCode = $this->getCheckout()->getCartCouponCode()) {
            $this->getQuote()->setCouponCode($couponCode);
        }
        return $this;
    }
}
