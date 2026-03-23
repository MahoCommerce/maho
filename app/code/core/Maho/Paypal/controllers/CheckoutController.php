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
                    $allowed = ['method', 'save_vault', 'vault_token_id', 'paypal_order_id', 'form_key'];
                    $this->getRequest()->setParams(array_intersect_key($data, array_flip($allowed)));
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
            if (!$this->_validateFormKey()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Invalid form key.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if (!$quote->getId() || !$quote->getItemsCount()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Cart is empty.'));
            }

            $methodCode = $this->_validateMethodCode(
                $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT),
            );

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
            $returnUrl = Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
            $cancelUrl = Mage::getUrl('checkout/cart', ['_secure' => true]);
            $shippingCallbackUrl = Mage::getUrl('paypal/checkout/shippingCallback', ['_secure' => true]);

            $orderRequest = $builder->buildFromQuote(
                $quote,
                $paypalIntent,
                returnUrl: $returnUrl,
                cancelUrl: $cancelUrl,
                vaultPaymentSource: $vaultPaymentSource,
                vaultPaypalTokenId: $vaultPaypalTokenId,
                vaultSourceType: $vaultSourceType,
                shippingCallbackUrl: $shippingCallbackUrl,
            );

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);
            $paypalOrder = $client->createOrder(['body' => $orderRequest]);

            $paypalOrderId = $paypalOrder['id'] ?? null;
            if (!$paypalOrderId) {
                Mage::log('PayPal createOrder response: ' . Mage::helper('core')->jsonEncode($paypalOrder), Mage::LOG_ERROR, 'paypal.log');
                $apiMessage = $paypalOrder['message'] ?? $paypalOrder['details'][0]['description'] ?? '';
                $errorMsg = $apiMessage
                    ? Mage::helper('maho_paypal')->__('Failed to create PayPal order: %s', $apiMessage)
                    : Mage::helper('maho_paypal')->__('Failed to create PayPal order.');
                Mage::throwException($errorMsg);
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
            if (!$this->_validateFormKey()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Invalid form key.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();

            if (!$quote->getIsActive()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('This order has already been placed.'));
            }

            $paypalOrderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');
            if (!$paypalOrderId) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Missing PayPal order ID.'));
            }
            $methodCode = $this->_validateMethodCode(
                $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT),
            );

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

            // Import address from PayPal if quote has no billing address (product page / cart shortcut flow)
            if (!$quote->getBillingAddress()->getFirstname()) {
                $this->_importPaypalAddress($paypalResult, $quote);
            }

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

    /**
     * Server-side callback called directly by PayPal when the buyer changes
     * their shipping address or selects a shipping option inside the popup.
     * PayPal POSTs order data and expects updated purchase_units in response.
     */
    public function shippingCallbackAction(): void
    {
        try {
            $body = $this->getRequest()->getRawBody();
            Mage::log('Shipping callback received: ' . $body, Mage::LOG_DEBUG, 'paypal.log');
            $data = Mage::helper('core')->jsonDecode($body);

            $paypalOrderId = $data['id'] ?? '';
            $shippingAddress = $data['shipping_address'] ?? [];
            $selectedOption = $data['shipping_option'] ?? null;

            // Find quote by PayPal order ID
            $quote = $this->_findQuoteByPaypalOrderId($paypalOrderId);
            if (!$quote || !$quote->getId() || $quote->isVirtual()) {
                Mage::log('Shipping callback: quote not found for order ' . $paypalOrderId, Mage::LOG_ERROR, 'paypal.log');
                $this->getResponse()->setHttpResponseCode(422);
                return;
            }

            $shippingAddr = $quote->getShippingAddress();

            // Apply address from PayPal (redacted: country, state, city, postal code)
            if ($shippingAddress) {
                $shippingAddr->setCountryId($shippingAddress['country_code'] ?? '');
                $shippingAddr->setRegion($shippingAddress['admin_area_1'] ?? '');
                $shippingAddr->setCity($shippingAddress['admin_area_2'] ?? '');
                $shippingAddr->setPostcode($shippingAddress['postal_code'] ?? '');
            }

            $shippingAddr->setCollectShippingRates(1)->collectShippingRates();
            $rates = $shippingAddr->getAllShippingRates();

            if (count($rates) === 0) {
                Mage::log('Shipping callback: no rates for address ' . Mage::helper('core')->jsonEncode($shippingAddress), Mage::LOG_ERROR, 'paypal.log');
                $this->getResponse()->setHttpResponseCode(422);
                return;
            }

            // If buyer selected a specific option, use it; otherwise select the first
            $selectedCode = $selectedOption['id'] ?? null;
            $validCodes = array_map(fn($r) => $r->getCode(), $rates);
            if ($selectedCode && in_array($selectedCode, $validCodes)) {
                $shippingAddr->setShippingMethod($selectedCode);
            } else {
                $shippingAddr->setShippingMethod($rates[0]->getCode());
            }

            $quote->collectTotals();
            $quote->save();

            // Build response in PayPal's expected format
            $currency = $quote->getBaseCurrencyCode();

            /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
            $builder = Mage::getModel('maho_paypal/api_orderBuilder');
            $breakdown = $builder->buildBreakdown($quote, $currency);

            $amount = [
                'currency_code' => $currency,
                'value' => $this->_formatAmount((float) $quote->getBaseGrandTotal()),
            ];
            if ($breakdown !== null) {
                $amount['breakdown'] = $breakdown;
            }

            $shippingOptions = [];
            $currentMethod = $shippingAddr->getShippingMethod();
            foreach ($rates as $rate) {
                $shippingOptions[] = [
                    'id' => $rate->getCode(),
                    'label' => trim($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(), ' -'),
                    'type' => 'SHIPPING',
                    'selected' => $rate->getCode() === $currentMethod,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $this->_formatAmount((float) $rate->getPrice()),
                    ],
                ];
            }

            $response = [
                'id' => $paypalOrderId,
                'purchase_units' => [
                    [
                        'reference_id' => 'default',
                        'amount' => $amount,
                        'shipping_options' => $shippingOptions,
                    ],
                ],
            ];

            $jsonResponse = Mage::helper('core')->jsonEncode($response);
            Mage::log('Shipping callback response: ' . $jsonResponse, Mage::LOG_DEBUG, 'paypal.log');
            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->getResponse()->setBody($jsonResponse);
        } catch (\Throwable $e) {
            Mage::log('Shipping callback exception: ' . $e->getMessage(), Mage::LOG_ERROR, 'paypal.log');
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(422);
        }
    }

    protected function _validateMethodCode(string $methodCode): string
    {
        $allowed = [
            Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_VAULT,
        ];
        if (!in_array($methodCode, $allowed, true)) {
            Mage::throwException(Mage::helper('maho_paypal')->__('Invalid payment method.'));
        }
        return $methodCode;
    }

    protected function _findQuoteByPaypalOrderId(string $paypalOrderId): ?Mage_Sales_Model_Quote
    {
        if (!$paypalOrderId || !preg_match('/^[A-Z0-9]+$/', $paypalOrderId)) {
            return null;
        }

        // First try session quote
        $sessionQuote = Mage::getSingleton('checkout/session')->getQuote();
        if ($sessionQuote->getPayment()->getAdditionalInformation('paypal_order_id') === $paypalOrderId) {
            return $sessionQuote;
        }

        // Fallback: search by the dedicated paypal_order_id column (PayPal callbacks have no session)
        /** @var Mage_Sales_Model_Resource_Quote_Payment_Collection $payments */
        $payments = Mage::getResourceModel('sales/quote_payment_collection');
        $payments->addFieldToFilter('paypal_order_id', $paypalOrderId);
        $payments->setPageSize(1);

        $payment = $payments->getFirstItem();
        if (!$payment->getId()) {
            return null;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->load($payment->getQuoteId());

        if (!$quote->getId() || !$quote->getIsActive()) {
            return null;
        }

        // Verify the payment's stored order ID matches exactly
        $storedOrderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');
        if ($storedOrderId !== $paypalOrderId) {
            return null;
        }

        return $quote;
    }

    protected function _formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
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

    protected function _importPaypalAddress(array $paypalResult, Mage_Sales_Model_Quote $quote): void
    {
        $payer = $paypalResult['payer'] ?? [];
        $shipping = $paypalResult['purchase_units'][0]['shipping'] ?? [];
        $paypalAddress = $shipping['address'] ?? [];
        $paypalName = $shipping['name']['full_name'] ?? '';

        if (!$paypalAddress) {
            return;
        }

        $nameParts = explode(' ', $paypalName, 2);
        $firstname = $nameParts[0] ?? '';
        $lastname = $nameParts[1] ?? $firstname;
        $email = $payer['email_address'] ?? '';
        if (!$email) {
            Mage::throwException(Mage::helper('maho_paypal')->__('PayPal did not return a payer email address.'));
        }

        $addressData = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'street' => implode("\n", array_filter([
                $paypalAddress['address_line_1'] ?? '',
                $paypalAddress['address_line_2'] ?? '',
            ])),
            'city' => $paypalAddress['admin_area_2'] ?? '',
            'region' => $paypalAddress['admin_area_1'] ?? '',
            'postcode' => $paypalAddress['postal_code'] ?? '',
            'country_id' => $paypalAddress['country_code'] ?? '',
            'telephone' => $payer['phone']['phone_number']['national_number'] ?? '0000000000',
        ];

        $billing = $quote->getBillingAddress();
        $billing->addData($addressData);
        $billing->setPaymentMethod($quote->getPayment()->getMethod());
        $billing->save();

        if (!$quote->isVirtual()) {
            $shippingAddr = $quote->getShippingAddress();
            $previousMethod = $shippingAddr->getShippingMethod();
            $shippingAddr->addData($addressData);
            $shippingAddr->setSameAsBilling(1);
            $shippingAddr->setCollectShippingRates(1)->collectShippingRates();

            $rates = $shippingAddr->getAllShippingRates();
            $availableCodes = array_map(fn($r) => $r->getCode(), $rates);

            if ($previousMethod && in_array($previousMethod, $availableCodes)) {
                $shippingAddr->setShippingMethod($previousMethod);
            } elseif (count($rates) > 0) {
                $shippingAddr->setShippingMethod($rates[0]->getCode());
            }
            $shippingAddr->save();
        }

        $quote->setCustomerEmail($email);
        $quote->setCustomerFirstname($firstname);
        $quote->setCustomerLastname($lastname);
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
