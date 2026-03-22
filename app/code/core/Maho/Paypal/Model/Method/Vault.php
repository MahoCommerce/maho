<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_Vault extends Maho_Paypal_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_VAULT;

    protected $_formBlockType = 'maho_paypal/checkout_vault_form';

    protected $_canUseInternal = true;

    #[\Override]
    public function isAvailable($quote = null): bool
    {
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
    public function initialize($paymentAction, $stateObject): self
    {
        $payment = $this->getInfoInstance();
        $vaultToken = $payment->getAdditionalInformation('paypal_vault_token');

        if ($vaultToken && !$payment->getAdditionalInformation('paypal_order_id')) {
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
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $payment->getQuote() ?: Mage::getSingleton('adminhtml/session_quote')->getQuote();

        $vaultTokenId = $payment->getAdditionalInformation('vault_token_id');
        /** @var Maho_Paypal_Model_Vault_Token $token */
        $token = Mage::getModel('maho_paypal/vault_token')->load($vaultTokenId);

        $intent = ($paymentAction === 'authorize') ? 'AUTHORIZE' : 'CAPTURE';
        $quote->reserveOrderId();

        /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
        $builder = Mage::getModel('maho_paypal/api_orderBuilder');
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
            Mage::throwException(Mage::helper('maho_paypal')->__('Failed to create PayPal order: %s', $e->getMessage()));
        }

        $paypalOrderId = $result['id'] ?? null;
        if (!$paypalOrderId) {
            Mage::throwException(Mage::helper('maho_paypal')->__('Failed to create PayPal order.'));
        }

        $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);

        $payments = $result['purchase_units'][0]['payments'] ?? [];
        $authId = $payments['authorizations'][0]['id'] ?? null;
        $captureId = $payments['captures'][0]['id'] ?? null;

        if (!$authId && !$captureId) {
            if ($intent === 'AUTHORIZE') {
                $result = $client->authorizeOrder($paypalOrderId);
                $authId = $result['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
            } else {
                $result = $client->captureOrder($paypalOrderId);
                $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
            }
        }

        if ($captureId) {
            $payment->setTransactionId($captureId);
            $payment->setIsTransactionClosed(true);
            $payment->setAdditionalInformation('paypal_capture_id', $captureId);
            $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
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
            /** @var Maho_Paypal_Model_Vault_Token $token */
            $token = Mage::getModel('maho_paypal/vault_token')->load($vaultTokenId);

            $customerId = (int) (Mage::getSingleton('customer/session')->getCustomerId()
                ?: Mage::getSingleton('adminhtml/session_quote')->getCustomerId());

            if ($token->getId() && (int) $token->getCustomerId() === $customerId) {
                $info->setAdditionalInformation('vault_token_id', $vaultTokenId);
                $info->setAdditionalInformation('paypal_vault_token', $token->getPaypalTokenId());
                $info->setAdditionalInformation('vault_label', $token->getDisplayLabel());
            }
        }

        return $this;
    }
}
