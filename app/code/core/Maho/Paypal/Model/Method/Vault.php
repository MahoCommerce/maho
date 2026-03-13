<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_Vault extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_VAULT;

    protected $_formBlockType = 'maho_paypal/checkout_vault_form';
    protected $_infoBlockType = 'maho_paypal/payment_info';

    protected $_isGateway = false;
    protected $_canOrder = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canFetchTransactionInfo = true;
    protected $_isInitializeNeeded = true;

    protected ?Maho_Paypal_Model_Api_Client $_apiClient = null;

    #[\Override]
    public function isAvailable($quote = null): bool
    {
        $config = $this->_getConfig();
        if (!$config->hasCredentials($quote?->getStoreId())) {
            return false;
        }

        $customerId = $quote?->getCustomerId()
            ?: Mage::getSingleton('customer/session')->getCustomerId();

        if (!$customerId) {
            return false;
        }

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $tokens */
        $tokens = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $tokens->addCustomerFilter((int) $customerId)->addActiveFilter();

        if ($tokens->getSize() === 0) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    #[\Override]
    public function assignData($data): self
    {
        if (!($data instanceof \Maho\DataObject)) {
            $data = new \Maho\DataObject($data);
        }

        $info = $this->getInfoInstance();
        $vaultTokenId = $data->getVaultTokenId();
        if ($vaultTokenId) {
            $info->setAdditionalInformation('vault_token_id', $vaultTokenId);

            /** @var Maho_Paypal_Model_Vault_Token $token */
            $token = Mage::getModel('maho_paypal/vault_token')->load($vaultTokenId);
            if ($token->getId()) {
                $info->setAdditionalInformation('paypal_vault_token', $token->getPaypalTokenId());
                $info->setAdditionalInformation('vault_label', $token->getDisplayLabel());
            }
        }

        return $this;
    }

    #[\Override]
    public function initialize($paymentAction, $stateObject): self
    {
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function authorize(\Maho\DataObject $payment, $amount): self
    {
        $paypalOrderId = $payment->getAdditionalInformation('paypal_order_id');
        if (!$paypalOrderId) {
            Mage::throwException(Mage::helper('maho_paypal')->__('PayPal order ID not found.'));
        }

        $result = $this->_getApiClient()->authorizeOrder($paypalOrderId);
        $authId = $result['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
        if ($authId) {
            $payment->setTransactionId($authId);
            $payment->setIsTransactionClosed(false);
            $payment->setAdditionalInformation('paypal_authorization_id', $authId);
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function capture(\Maho\DataObject $payment, $amount): self
    {
        $authId = $payment->getAdditionalInformation('paypal_authorization_id')
            ?: $payment->getParentTransactionId();
        $paypalOrderId = $payment->getAdditionalInformation('paypal_order_id');

        if ($authId) {
            $body = [];
            if ($amount != $payment->getOrder()->getBaseGrandTotal()) {
                $body = [
                    'amount' => [
                        'value' => number_format((float) $amount, 2, '.', ''),
                        'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
                    ],
                    'final_capture' => true,
                ];
            }
            $result = $this->_getApiClient()->captureAuthorization($authId, $body);
            $captureId = $result['id'] ?? null;
        } elseif ($paypalOrderId) {
            $result = $this->_getApiClient()->captureOrder($paypalOrderId);
            $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        } else {
            Mage::throwException(Mage::helper('maho_paypal')->__('No PayPal authorization or order ID found for capture.'));
        }

        if ($captureId) {
            $payment->setTransactionId($captureId);
            $payment->setIsTransactionClosed(true);
            $payment->setAdditionalInformation('paypal_capture_id', $captureId);
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function refund(\Maho\DataObject $payment, $amount): self
    {
        $captureId = $payment->getAdditionalInformation('paypal_capture_id')
            ?: $payment->getParentTransactionId();

        if (!$captureId) {
            Mage::throwException(Mage::helper('maho_paypal')->__('No PayPal capture ID found for refund.'));
        }

        $body = [
            'amount' => [
                'value' => number_format((float) $amount, 2, '.', ''),
                'currency_code' => $payment->getOrder()->getBaseCurrencyCode(),
            ],
        ];

        $result = $this->_getApiClient()->refundCapture($captureId, $body);
        $refundId = $result['id'] ?? null;
        if ($refundId) {
            $payment->setTransactionId($refundId);
            $payment->setIsTransactionClosed(true);
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function void(\Maho\DataObject $payment): self
    {
        $authId = $payment->getAdditionalInformation('paypal_authorization_id')
            ?: $payment->getParentTransactionId();

        if (!$authId) {
            Mage::throwException(Mage::helper('maho_paypal')->__('No PayPal authorization ID found for void.'));
        }

        $this->_getApiClient()->voidAuthorization($authId);
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function cancel(\Maho\DataObject $payment): self
    {
        return $this->void($payment);
    }

    protected function _getConfig(): Maho_Paypal_Model_Config
    {
        $model = Mage::getModel('paypal/config');
        return $model;
    }

    protected function _getApiClient(): Maho_Paypal_Model_Api_Client
    {
        if ($this->_apiClient === null) {
            $storeId = null;
            if ($this->getInfoInstance()) {
                $storeId = (int) $this->getInfoInstance()->getOrder()->getStoreId();
            }
            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', $storeId ? ['store_id' => $storeId] : []);
            $this->_apiClient = $client;
        }
        return $this->_apiClient;
    }
}
