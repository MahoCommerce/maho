<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_Express extends Mage_Payment_Model_Method_Abstract implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code  = Mage_Paypal_Model_Config::METHOD_WPP_EXPRESS;
    protected $_formBlockType = 'paypal/express_form';
    protected $_infoBlockType = 'paypal/payment_info';

    /**
     * Website Payments Pro instance type
     *
     * @var string $_proType
     */
    protected $_proType = 'paypal/pro';

    /**
     * Availability options
     */
    protected $_isGateway                   = false;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canCreateBillingAgreement   = true;
    protected $_canReviewPayment            = true;

    /**
     * Website Payments Pro instance
     *
     * @var Mage_Paypal_Model_Pro
     */
    protected $_pro = null;

    /**
     * Payment additional information key for payment action
     * @var string
     */
    protected $_isOrderPaymentActionKey = 'is_order_action';

    /**
     * Payment additional information key for number of used authorizations
     * @var string
     */
    protected $_authorizationCountKey = 'authorization_count';

    public function __construct($params = [])
    {
        $proInstance = array_shift($params);
        if ($proInstance instanceof Mage_Paypal_Model_Pro) {
            $this->_pro = $proInstance;
        } else {
            /** @var Mage_Paypal_Model_Pro $model */
            $model = Mage::getModel($this->_proType);
            $this->_pro = $model;
        }
        $this->_pro->setMethod($this->_code);
        $this->_setApiProcessableErrors();
    }

    /**
     * Set processable error codes to API model
     *
     * @return Mage_Paypal_Model_Api_Nvp
     */
    protected function _setApiProcessableErrors()
    {
        return $this->_pro->getApi()->setProcessableErrors(
            [
                Mage_Paypal_Model_Api_ProcessableException::API_INTERNAL_ERROR,
                Mage_Paypal_Model_Api_ProcessableException::API_UNABLE_PROCESS_PAYMENT_ERROR_CODE,
                Mage_Paypal_Model_Api_ProcessableException::API_DO_EXPRESS_CHECKOUT_FAIL,
                Mage_Paypal_Model_Api_ProcessableException::API_UNABLE_TRANSACTION_COMPLETE,
                Mage_Paypal_Model_Api_ProcessableException::API_TRANSACTION_EXPIRED,
                Mage_Paypal_Model_Api_ProcessableException::API_MAX_PAYMENT_ATTEMPTS_EXCEEDED,
                Mage_Paypal_Model_Api_ProcessableException::API_COUNTRY_FILTER_DECLINE,
                Mage_Paypal_Model_Api_ProcessableException::API_MAXIMUM_AMOUNT_FILTER_DECLINE,
                Mage_Paypal_Model_Api_ProcessableException::API_OTHER_FILTER_DECLINE,
            ],
        );
    }

    /**
     * Store setter
     * Also updates store ID in config object
     *
     * @param Mage_Core_Model_Store|int $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        if ($store === null) {
            $store = Mage::app()->getStore()->getId();
        }
        $this->_pro->getConfig()->setStoreId(is_object($store) ? $store->getId() : $store);
        return $this;
    }

    /**
     * Can be used in regular checkout
     *
     * @return bool
     */
    #[\Override]
    public function canUseCheckout()
    {
        if (Mage::getStoreConfigFlag('payment/hosted_pro/active')
            && !Mage::getStoreConfigFlag('payment/hosted_pro/display_ec')
        ) {
            return false;
        }
        return parent::canUseCheckout();
    }

    /**
     * Whether method is available for specified currency
     *
     * @param string $currencyCode
     * @return bool
     */
    #[\Override]
    public function canUseForCurrency($currencyCode)
    {
        return $this->_pro->getConfig()->isCurrencyCodeSupported($currencyCode);
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @see Mage_Sales_Model_Payment::place()
     * @return string
     */
    #[\Override]
    public function getConfigPaymentAction()
    {
        return $this->_pro->getConfig()->getPaymentAction();
    }

    /**
     * Check whether payment method can be used
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    #[\Override]
    public function isAvailable($quote = null)
    {
        if (parent::isAvailable($quote) && $this->_pro->getConfig()->isMethodAvailable()) {
            return true;
        }
        return false;
    }

    /**
     * Custom getter for payment configuration
     *
     * @param string $field
     * @param int $storeId
     * @return mixed
     */
    #[\Override]
    public function getConfigData($field, $storeId = null)
    {
        return $this->_pro->getConfig()->$field;
    }

    /**
     * Order payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function order(Varien_Object $payment, $amount)
    {
        $paypalTransactionData = Mage::getSingleton('checkout/session')->getPaypalTransactionData();
        if (!is_array($paypalTransactionData)) {
            $this->_placeOrder($payment, $amount);
        } else {
            $this->_importToPayment($this->_pro->getApi()->setData($paypalTransactionData), $payment);
        }

        $payment->setAdditionalInformation($this->_isOrderPaymentActionKey, true);

        if ($payment->getIsFraudDetected()) {
            return $this;
        }

        $order = $payment->getOrder();
        $orderTransactionId = $payment->getTransactionId();

        $api = $this->_callDoAuthorize($amount, $payment, $orderTransactionId);

        $state  = Mage_Sales_Model_Order::STATE_PROCESSING;
        $status = true;

        $formatedPrice = $order->getBaseCurrency()->formatTxt($amount);
        if ($payment->getIsTransactionPending()) {
            $message = Mage::helper('paypal')->__('Ordering amount of %s is pending approval on gateway.', $formatedPrice);
            $state = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
        } else {
            $message = Mage::helper('paypal')->__('Ordered amount of %s.', $formatedPrice);
        }

        $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, $message);

        $this->_pro->importPaymentInfo($api, $payment);

        if ($payment->getIsTransactionPending()) {
            $message = Mage::helper('paypal')->__('Authorizing amount of %s is pending approval on gateway.', $formatedPrice);
            $state = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
            if ($payment->getIsFraudDetected()) {
                $status = Mage_Sales_Model_Order::STATUS_FRAUD;
            }
        } else {
            $message = Mage::helper('paypal')->__('Authorized amount of %s.', $formatedPrice);
        }

        $payment->resetTransactionAdditionalInfo();

        $payment->setTransactionId($api->getTransactionId());
        $payment->setParentTransactionId($orderTransactionId);

        $transaction = $payment->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            false,
            $message,
        );

        $order->setState($state, $status);

        $payment->setSkipOrderProcessing(true);
        return $this;
    }

    /**
     * Authorize payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function authorize(Varien_Object $payment, $amount)
    {
        return $this->_placeOrder($payment, $amount);
    }

    /**
     * Void payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    #[\Override]
    public function void(Varien_Object $payment)
    {
        //Switching to order transaction if needed
        if ($payment->getAdditionalInformation($this->_isOrderPaymentActionKey)
            && !$payment->getVoidOnlyAuthorization()
        ) {
            $orderTransaction = $payment->lookupTransaction(
                false,
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER,
            );
            if ($orderTransaction) {
                $payment->setParentTransactionId($orderTransaction->getTxnId());
                $payment->setTransactionId($orderTransaction->getTxnId() . '-void');
            }
        }
        $this->_pro->void($payment);
        return $this;
    }

    /**
     * Capture payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function capture(Varien_Object $payment, $amount)
    {
        $authorizationTransaction = $payment->getAuthorizationTransaction();
        $authorizationPeriod = abs((int) $this->getConfigData('authorization_honor_period'));
        $maxAuthorizationNumber = abs((int) $this->getConfigData('child_authorization_number'));
        $order = $payment->getOrder();
        $isAuthorizationCreated = false;

        if ($payment->getAdditionalInformation($this->_isOrderPaymentActionKey)) {
            $voided = false;
            if (!$authorizationTransaction->getIsClosed()
                && $this->_isTransactionExpired($authorizationTransaction, $authorizationPeriod)
            ) {
                //Save payment state and configure payment object for voiding
                $isCaptureFinal = $payment->getShouldCloseParentTransaction();
                $captureTrxId = $payment->getTransactionId();
                $payment->setShouldCloseParentTransaction(false);
                $payment->setParentTransactionId($authorizationTransaction->getTxnId());
                $payment->unsTransactionId();
                $payment->setVoidOnlyAuthorization(true);
                $payment->void(new Varien_Object());

                //Revert payment state after voiding
                $payment->unsAuthorizationTransaction();
                $payment->unsTransactionId();
                $payment->setShouldCloseParentTransaction($isCaptureFinal);
                $voided = true;
            }

            if ($authorizationTransaction->getIsClosed() || $voided) {
                if ($payment->getAdditionalInformation($this->_authorizationCountKey) > $maxAuthorizationNumber - 1) {
                    Mage::throwException(Mage::helper('paypal')->__('The maximum number of child authorizations is reached.'));
                }
                $api = $this->_callDoAuthorize(
                    $amount,
                    $payment,
                    $authorizationTransaction->getParentTxnId(),
                );

                //Adding authorization transaction
                $this->_pro->importPaymentInfo($api, $payment);
                $payment->setTransactionId($api->getTransactionId());
                $payment->setParentTransactionId($authorizationTransaction->getParentTxnId());
                $payment->setIsTransactionClosed(false);

                $formatedPrice = $order->getBaseCurrency()->formatTxt($amount);

                if ($payment->getIsTransactionPending()) {
                    $message = Mage::helper('paypal')->__('Authorizing amount of %s is pending approval on gateway.', $formatedPrice);
                } else {
                    $message = Mage::helper('paypal')->__('Authorized amount of %s.', $formatedPrice);
                }

                $transaction = $payment->addTransaction(
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                    null,
                    true,
                    $message,
                );

                $payment->setParentTransactionId($api->getTransactionId());
                $isAuthorizationCreated = true;
            }
            //close order transaction if needed
            if ($payment->getShouldCloseParentTransaction()) {
                $orderTransaction = $payment->lookupTransaction(
                    false,
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER,
                );

                if ($orderTransaction) {
                    $orderTransaction->setIsClosed(true);
                    $order->addRelatedObject($orderTransaction);
                }
            }
        }

        if ($this->_pro->capture($payment, $amount) === false) {
            $this->_placeOrder($payment, $amount);
        }

        if ($isAuthorizationCreated && isset($transaction)) {
            $transaction->setIsClosed(true);
        }

        return $this;
    }

    /**
     * Refund capture
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function refund(Varien_Object $payment, $amount)
    {
        $this->_pro->refund($payment, $amount);
        return $this;
    }

    /**
     * Cancel payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     */
    #[\Override]
    public function cancel(Varien_Object $payment)
    {
        $this->void($payment);

        return $this;
    }

    /**
     * Whether payment can be reviewed
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    #[\Override]
    public function canReviewPayment(Mage_Payment_Model_Info $payment)
    {
        return parent::canReviewPayment($payment) && $this->_pro->canReviewPayment($payment);
    }

    /**
     * Attempt to accept a pending payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    #[\Override]
    public function acceptPayment(Mage_Payment_Model_Info $payment)
    {
        parent::acceptPayment($payment);
        return $this->_pro->reviewPayment($payment, Mage_Paypal_Model_Pro::PAYMENT_REVIEW_ACCEPT);
    }

    /**
     * Attempt to deny a pending payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    #[\Override]
    public function denyPayment(Mage_Payment_Model_Info $payment)
    {
        parent::denyPayment($payment);
        return $this->_pro->reviewPayment($payment, Mage_Paypal_Model_Pro::PAYMENT_REVIEW_DENY);
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see Mage_Checkout_OnepageController::savePaymentAction()
     * @see Mage_Sales_Model_Quote_Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('paypal/express/start');
    }

    /**
     * Fetch transaction details info
     *
     * @param string $transactionId
     * @return array
     */
    #[\Override]
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        return $this->_pro->fetchTransactionInfo($payment, $transactionId);
    }

    /**
     * Validate RP data
     */
    #[\Override]
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this->_pro->validateRecurringProfile($profile);
    }

    /**
     * Submit RP to the gateway
     */
    #[\Override]
    public function submitRecurringProfile(
        Mage_Payment_Model_Recurring_Profile $profile,
        Mage_Payment_Model_Info $paymentInfo,
    ) {
        $token = $paymentInfo->
            getAdditionalInformation(Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_TOKEN);
        $profile->setToken($token);
        $this->_pro->submitRecurringProfile($profile, $paymentInfo);
    }

    /**
     * Fetch RP details
     *
     * @param string $referenceId
     */
    #[\Override]
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        return $this->_pro->getRecurringProfileDetails($referenceId, $result);
    }

    /**
     * Whether can get recurring profile details
     */
    #[\Override]
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update RP data
     */
    #[\Override]
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this->_pro->updateRecurringProfile($profile);
    }

    /**
     * Manage status
     */
    #[\Override]
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this->_pro->updateRecurringProfileStatus($profile);
    }

    #[\Override]
    public function assignData($data)
    {
        $result = parent::assignData($data);
        $key = Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT;
        if (is_array($data)) {
            $this->getInfoInstance()->setAdditionalInformation($key, $data[$key] ?? null);
        } elseif ($data instanceof Varien_Object) {
            $this->getInfoInstance()->setAdditionalInformation($key, $data->getData($key));
        }
        return $result;
    }

    /**
     * Place an order with authorization or capture action
     *
     * @param float $amount
     * @return $this
     */
    protected function _placeOrder(Mage_Sales_Model_Order_Payment $payment, $amount)
    {
        $order = $payment->getOrder();

        // prepare api call
        $token = $payment->getAdditionalInformation(Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_TOKEN);
        $api = $this->_pro->getApi()
            ->setToken($token)
            ->setPayerId($payment->
                getAdditionalInformation(Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_PAYER_ID))
            ->setAmount($amount)
            ->setPaymentAction($this->_pro->getConfig()->paymentAction)
            ->setNotifyUrl(Mage::getUrl('paypal/ipn/'))
            ->setInvNum($order->getIncrementId())
            ->setCurrencyCode($order->getBaseCurrencyCode())
            ->setPaypalCart(Mage::getModel('paypal/cart', [$order]))
            ->setIsLineItemsEnabled($this->_pro->getConfig()->lineItemsEnabled);

        // call api and get details from it
        $api->callDoExpressCheckoutPayment();

        $this->_importToPayment($api, $payment);
        return $this;
    }

    /**
     * Import payment info to payment
     *
     * @param Mage_Paypal_Model_Api_Nvp $api
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    protected function _importToPayment($api, $payment)
    {
        $payment->setTransactionId($api->getTransactionId())->setIsTransactionClosed(0)
            ->setAdditionalInformation(
                Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_REDIRECT,
                $api->getRedirectRequired(),
            );

        if ($api->getBillingAgreementId()) {
            $payment->setBillingAgreementData([
                'billing_agreement_id'  => $api->getBillingAgreementId(),
                'method_code'           => Mage_Paypal_Model_Config::METHOD_BILLING_AGREEMENT,
            ]);
        }

        $this->_pro->importPaymentInfo($api, $payment);
    }

    /**
     * Check void availability
     *
     * @return  bool
     */
    #[\Override]
    public function canVoid(Varien_Object $payment)
    {
        if ($payment instanceof Mage_Sales_Model_Order_Invoice
            || $payment instanceof Mage_Sales_Model_Order_Creditmemo
        ) {
            return false;
        }
        $info = $this->getInfoInstance();
        if ($info->getAdditionalInformation($this->_isOrderPaymentActionKey)) {
            $orderTransaction = $info->lookupTransaction(
                false,
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER,
            );
            if ($orderTransaction) {
                $info->setParentTransactionId($orderTransaction->getTxnId());
            }
        }

        return $this->_canVoid;
    }

    /**
     * Check capture availability
     *
     * @return bool
     */
    #[\Override]
    public function canCapture()
    {
        $payment = $this->getInfoInstance();
        $this->_pro->getConfig()->setStoreId($payment->getOrder()->getStore()->getId());

        if ($payment->getAdditionalInformation($this->_isOrderPaymentActionKey)) {
            $orderTransaction = $payment->lookupTransaction(
                false,
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER,
            );
            if ($orderTransaction->getIsClosed()) {
                return false;
            }

            $orderValidPeriod = abs((int) $this->getConfigData('order_valid_period'));

            $dateCompass = new DateTime($orderTransaction->getCreatedAt());
            $dateCompass->modify('+' . $orderValidPeriod . ' days');
            $currentDate = new DateTime();

            if ($currentDate > $dateCompass || $orderValidPeriod == 0) {
                return false;
            }
        }
        return $this->_canCapture;
    }

    /**
     * Call DoAuthorize
     *
     * @param int $amount
     * @param Varien_Object $payment
     * @param string $parentTransactionId
     * @return Mage_Paypal_Model_Api_Abstract
     * @throws Mage_Paypal_Model_Api_ProcessableException
     */
    protected function _callDoAuthorize($amount, $payment, $parentTransactionId)
    {
        $apiData = $this->_pro->getApi()->getData();
        foreach ($apiData as $k => $v) {
            if (is_object($v)) {
                unset($apiData[$k]);
            }
        }
        Mage::getSingleton('checkout/session')->setPaypalTransactionData($apiData);
        $this->_pro->resetApi();
        $api = $this->_setApiProcessableErrors()
            ->setAmount($amount)
            ->setCurrencyCode($payment->getOrder()->getBaseCurrencyCode())
            ->setTransactionId($parentTransactionId)
            ->callDoAuthorization();

        $payment->setAdditionalInformation(
            $this->_authorizationCountKey,
            $payment->getAdditionalInformation($this->_authorizationCountKey) + 1,
        );

        return $api;
    }

    /**
     * Check transaction for expiration in PST
     *
     * @param int $period
     * @return bool
     */
    protected function _isTransactionExpired(Mage_Sales_Model_Order_Payment_Transaction $transaction, $period)
    {
        $period = (int) $period;
        if ($period == 0) {
            return true;
        }

        $transactionClosingDate = new DateTime($transaction->getCreatedAt(), new DateTimeZone('GMT'));
        $transactionClosingDate->setTimezone(new DateTimeZone('US/Pacific'));
        /**
         * 11:49:00 PayPal transactions closing time
         */
        $transactionClosingDate->setTime(11, 49, 00);
        $transactionClosingDate->modify('+' . $period . ' days');

        $currentTime = new DateTime(null, new DateTimeZone('US/Pacific'));

        if ($currentTime > $transactionClosingDate) {
            return true;
        }

        return false;
    }
}
