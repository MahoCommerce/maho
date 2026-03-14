<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_CheckoutController extends Mage_Core_Controller_Front_Action
{
    /**
     * Parse JSON request body params into the request object
     */
    #[\Override]
    public function preDispatch(): static
    {
        parent::preDispatch();

        $contentType = $this->getRequest()->getHeader('Content-Type');
        if ($contentType && str_contains($contentType, 'application/json')) {
            $body = $this->getRequest()->getRawBody();
            if ($body) {
                $data = Mage::helper('core')->jsonDecode($body);
                if (is_array($data)) {
                    $this->getRequest()->setParams($data);
                }
            }
        }

        return $this;
    }

    public function clientTokenAction(): void
    {
        $result = ['success' => false];

        try {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $storeId = $quote->getStoreId() ? (int) $quote->getStoreId() : null;

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => $storeId]);
            $clientToken = $client->generateClientToken();

            $result['success'] = true;
            $result['client_token'] = $clientToken;
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function createOrderAction(): void
    {
        $result = ['success' => false];

        try {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if (!$quote->getId() || !$quote->getItemsCount()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Cart is empty.'));
            }

            $methodCode = $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);

            /** @var Maho_Paypal_Model_Config $config */
            $config = Mage::getModel('paypal/config');
            $intent = $config->getNewPaymentAction($methodCode);
            $paypalIntent = ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) ? 'CAPTURE' : 'AUTHORIZE';

            $vaultPaymentSource = null;
            if ($this->getRequest()->getParam('save_vault') && $quote->getCustomerId()) {
                $vaultPaymentSource = match ($methodCode) {
                    Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT => 'paypal',
                    Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT => 'card',
                    default => null,
                };
            }

            // For vault payments, resolve the PayPal token ID
            $vaultTokenId = $this->getRequest()->getParam('vault_token_id');
            $vaultPaypalTokenId = null;
            $vaultSourceType = null;
            if ($vaultTokenId && $methodCode === Maho_Paypal_Model_Config::METHOD_VAULT) {
                /** @var Maho_Paypal_Model_Vault_Token $vaultToken */
                $vaultToken = Mage::getModel('maho_paypal/vault_token')->load($vaultTokenId);
                if ($vaultToken->getId() && $vaultToken->getIsActive()
                    && (int) $vaultToken->getCustomerId() === (int) $quote->getCustomerId()) {
                    $vaultPaypalTokenId = $vaultToken->getPaypalTokenId();
                    $vaultSourceType = $vaultToken->getPaymentSourceType();
                }
            }

            /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
            $builder = Mage::getModel('maho_paypal/api_orderBuilder');
            $orderRequest = $builder->buildFromQuote(
                $quote,
                $paypalIntent,
                vaultPaymentSource: $vaultPaymentSource,
                vaultPaypalTokenId: $vaultPaypalTokenId,
                vaultSourceType: $vaultSourceType,
            );

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);
            $paypalOrder = $client->createOrder(['body' => $orderRequest]);

            $paypalOrderId = $paypalOrder['id'] ?? null;
            if (!$paypalOrderId) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Failed to create PayPal order.'));
            }

            // Store PayPal order ID on quote payment
            $quote->getPayment()->setAdditionalInformation('paypal_order_id', $paypalOrderId);
            $quote->getPayment()->setData('paypal_order_id', $paypalOrderId);
            $quote->getPayment()->save();

            $result['success'] = true;
            $result['paypal_order_id'] = $paypalOrderId;

            $billingAddress = $quote->getBillingAddress();
            if ($billingAddress && $billingAddress->getFirstname()) {
                $street = $billingAddress->getStreet();
                $result['billing_address'] = [
                    'addressLine1' => $street[0] ?? '',
                    'addressLine2' => $street[1] ?? '',
                    'adminArea1' => (string) $billingAddress->getRegionCode(),
                    'adminArea2' => (string) $billingAddress->getCity(),
                    'countryCode' => (string) $billingAddress->getCountryId(),
                    'postalCode' => (string) $billingAddress->getPostcode(),
                ];
            }
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Approve (authorize or capture) a PayPal order and place the Mage order
     */
    public function approveOrderAction(): void
    {
        $result = ['success' => false];

        try {
            $paypalOrderId = $this->getRequest()->getParam('paypal_order_id');
            if (!$paypalOrderId) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Missing PayPal order ID.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $methodCode = $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);

            /** @var Maho_Paypal_Model_Config $config */
            $config = Mage::getModel('paypal/config');
            $intent = $config->getNewPaymentAction($methodCode);

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);

            // Fetch current order status first
            $paypalResult = $client->getOrder($paypalOrderId);
            $status = $paypalResult['status'] ?? '';

            // If order is not yet completed (standard/advanced checkout flow),
            // authorize or capture it now
            if ($status !== 'COMPLETED') {
                if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
                    $paypalResult = $client->captureOrder($paypalOrderId);
                } else {
                    $paypalResult = $client->authorizeOrder($paypalOrderId);
                }
                $status = $paypalResult['status'] ?? '';
            }

            if (!in_array($status, ['COMPLETED', 'APPROVED'])) {
                Mage::throwException(Mage::helper('maho_paypal')->__('PayPal order could not be approved. Status: %s', $status));
            }

            // Set payment method on quote
            $payment = $quote->getPayment();
            $payment->setMethod($methodCode);
            $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
            $payment->setData('paypal_order_id', $paypalOrderId);

            // Import transaction IDs
            $this->_importTransactionIds($paypalResult, $payment, $intent);

            // Import payer info
            $payer = $paypalResult['payer'] ?? [];
            if (!empty($payer['email_address'])) {
                $payment->setAdditionalInformation('payer_email', $payer['email_address']);
            }
            if (!empty($payer['payer_id'])) {
                $payment->setAdditionalInformation('payer_id', $payer['payer_id']);
            }

            // Persist payment data before saveOrder() which may reimport payment
            $payment->save();

            // Save vault token if returned by PayPal
            $this->_saveVaultToken($paypalResult, $quote);

            // Place the Magento order
            $quote->collectTotals();

            /** @var Mage_Checkout_Model_Type_Onepage $onepage */
            $onepage = Mage::getSingleton('checkout/type_onepage');
            $onepage->saveOrder();

            // Deactivate quote and persist to DB
            $quote->setIsActive(0);
            $quote->save();

            $result['success'] = true;
            $result['redirect_url'] = Mage::getUrl('checkout/onepage/success', ['_secure' => true]);
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    protected function _importTransactionIds(array $paypalResult, Mage_Sales_Model_Quote_Payment $payment, string $intent): void
    {
        $purchaseUnit = $paypalResult['purchase_units'][0] ?? [];
        $payments = $purchaseUnit['payments'] ?? [];

        if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
            $captureId = $payments['captures'][0]['id'] ?? null;
            if ($captureId) {
                $payment->setAdditionalInformation('paypal_capture_id', $captureId);
            }
        }

        $authId = $payments['authorizations'][0]['id'] ?? null;
        if ($authId) {
            $payment->setAdditionalInformation('paypal_authorization_id', $authId);
        }
    }

    protected function _saveVaultToken(array $paypalResult, Mage_Sales_Model_Quote $quote): void
    {
        $customerId = $quote->getCustomerId();
        if (!$customerId) {
            return;
        }

        $paymentSource = $paypalResult['payment_source'] ?? [];
        $sourceType = null;
        $vaultData = null;
        $cardLastFour = null;
        $cardBrand = null;
        $cardExpiry = null;
        $payerEmail = null;

        if (isset($paymentSource['card']['attributes']['vault'])) {
            $vaultData = $paymentSource['card']['attributes']['vault'];
            $sourceType = 'card';
            $cardLastFour = $paymentSource['card']['last_digits'] ?? null;
            $cardBrand = $paymentSource['card']['brand'] ?? null;
            $cardExpiry = $paymentSource['card']['expiry'] ?? null;
        } elseif (isset($paymentSource['paypal']['attributes']['vault'])) {
            $vaultData = $paymentSource['paypal']['attributes']['vault'];
            $sourceType = 'paypal';
            $payerEmail = $paymentSource['paypal']['email_address'] ?? null;
        }

        if (!$vaultData || ($vaultData['status'] ?? '') !== 'VAULTED') {
            return;
        }

        $paypalTokenId = $vaultData['id'] ?? '';
        if (!$paypalTokenId) {
            return;
        }

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $existing */
        $existing = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $existing->addFieldToFilter('paypal_token_id', $paypalTokenId);
        if ($existing->getSize() > 0) {
            return;
        }

        // Deactivate older tokens for the same payment method
        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $oldTokens */
        $oldTokens = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $oldTokens->addCustomerFilter((int) $customerId)->addActiveFilter();
        $oldTokens->addFieldToFilter('payment_source_type', $sourceType);
        if ($sourceType === 'card') {
            $oldTokens->addFieldToFilter('card_last_four', $cardLastFour);
            $oldTokens->addFieldToFilter('card_brand', $cardBrand);
        } elseif ($sourceType === 'paypal' && $payerEmail) {
            $oldTokens->addFieldToFilter('payer_email', $payerEmail);
        }
        foreach ($oldTokens as $oldToken) {
            $oldToken->setIsActive(0)->save();
        }

        /** @var Maho_Paypal_Model_Vault_Token $token */
        $token = Mage::getModel('maho_paypal/vault_token');
        $token->setData([
            'customer_id' => (int) $customerId,
            'paypal_token_id' => $paypalTokenId,
            'payment_source_type' => $sourceType,
            'card_last_four' => $cardLastFour,
            'card_brand' => $cardBrand,
            'card_expiry' => $cardExpiry,
            'payer_email' => $payerEmail,
        ]);
        $token->save();
    }
}
