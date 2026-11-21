<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_Vault extends Maho_Paypal_Model_Method_Abstract
{
    #[\Override]
    protected $_code = Maho_Paypal_Model_Config::METHOD_VAULT;

    #[\Override]
    protected $_formBlockType = 'paypal/checkout_vault_form';

    #[\Override]
    protected $_canUseInternal = true;

    #[\Override]
    public function isAvailable($quote = null): bool
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        $customerId = $quote?->getCustomerId()
            ?: Mage::getSingleton('customer/session')->getCustomerId();

        if (!$customerId) {
            return false;
        }

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $tokens */
        $tokens = Mage::getResourceModel('paypal/vault_token_collection');
        return $tokens
            ->addCustomerFilter((int) $customerId)
            ->addActiveFilter()
            ->getSize() > 0;
    }

    #[\Override]
    public function initialize($paymentAction, $stateObject): self
    {
        $payment = $this->getInfoInstance();
        $vaultTokenId = $payment->getAdditionalInformation('vault_token_id');

        if ($vaultTokenId && !$payment->getAdditionalInformation('paypal_order_id')) {
            $this->_processAdminVaultOrder($payment, $paymentAction, $stateObject);
            return $this;
        }

        return parent::initialize($paymentAction, $stateObject);
    }

    protected function _processAdminVaultOrder(
        Mage_Sales_Model_Order_Payment $payment,
        string $paymentAction,
        \Maho\DataObject $stateObject,
    ): void {
        $token = $this->_getCustomerToken(
            $payment->getAdditionalInformation('vault_token_id'),
            (int) $payment->getOrder()->getCustomerId(),
        );
        if (!$token) {
            Mage::throwException(Mage::helper('paypal')->__('No saved payment methods available for this customer.'));
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $payment->getQuote() ?: Mage::getSingleton('adminhtml/session_quote')->getQuote();

        $intent = ($paymentAction === 'authorize') ? 'AUTHORIZE' : 'CAPTURE';
        $quote->reserveOrderId();

        /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
        $builder = Mage::getModel('paypal/api_orderBuilder');
        $orderRequest = $builder->buildFromQuote(
            $quote,
            $intent,
            vaultPaypalTokenId: $token->getPaypalTokenId(),
            vaultSourceType: $token->getPaymentSourceType(),
        );

        $client = $this->_getApiClient();

        try {
            $result = $client->createOrder(['body' => $orderRequest]);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::throwException(Mage::helper('paypal')->__('Failed to create PayPal order: %s', $e->getMessage()));
        }

        $paypalOrderId = $result['id'] ?? null;
        if (!$paypalOrderId) {
            Mage::throwException(Mage::helper('paypal')->__('Failed to create PayPal order.'));
        }

        $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);

        $payments = $result['purchase_units'][0]['payments'] ?? [];
        $authId = $payments['authorizations'][0]['id'] ?? null;
        $capture = $payments['captures'][0] ?? [];
        $captureId = $capture['id'] ?? null;

        if (!$authId && !$captureId) {
            if ($intent === 'AUTHORIZE') {
                $result = $client->authorizeOrder($paypalOrderId);
                $authId = $result['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
            } else {
                $result = $client->captureOrder($paypalOrderId);
                $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
                $captureId = $capture['id'] ?? null;
            }
        }

        if ($captureId) {
            $payment->setTransactionId($captureId);
            $payment->setIsTransactionClosed(true);
            Mage::helper('paypal')->setCaptureInformation($payment, $capture);
            $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            $this->_createCaptureInvoice($payment, $captureId);
        } elseif ($authId) {
            $payment->setTransactionId($authId);
            $payment->setIsTransactionClosed(false);
            $payment->setAdditionalInformation('paypal_authorization_id', $authId);
            $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
        }

        $this->_importPaymentInfo($result, $payment);

        $stateObject->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
        $stateObject->setStatus('processing');
        $stateObject->setIsNotified(true);
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
            // Quote customer first: stateless API flows have no session
            $quoteCustomerId = $info instanceof Mage_Sales_Model_Quote_Payment
                ? (int) $info->getQuote()->getCustomerId()
                : 0;
            $customerId = $quoteCustomerId
                ?: (int) (Mage::getSingleton('customer/session')->getCustomerId()
                    ?: Mage::getSingleton('adminhtml/session_quote')->getCustomerId());

            // A token that the customer cannot use also clears the token of an earlier selection
            $token = $this->_getCustomerToken($vaultTokenId, $customerId);
            $info->setAdditionalInformation('vault_token_id', $token ? $vaultTokenId : null);
            $info->setAdditionalInformation('vault_label', $token?->getDisplayLabel());
        }

        return $this;
    }

    /**
     * Return the vault token $tokenId when the customer $customerId can use it, or null.
     */
    protected function _getCustomerToken(mixed $tokenId, int $customerId): ?Maho_Paypal_Model_Vault_Token
    {
        /** @var Maho_Paypal_Model_Vault_Token $token */
        $token = Mage::getModel('paypal/vault_token')->load($tokenId);

        return $token->isUsableBy($customerId) ? $token : null;
    }
}
