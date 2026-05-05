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

    #[Maho\Config\Route('/paypal/checkout/clientToken', methods: ['POST'])]
    public function clientTokenAction(): void
    {
        $result = ['success' => false];

        try {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $storeId = $quote->getStoreId() ? (int) $quote->getStoreId() : null;

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('paypal/api_client', ['store_id' => $storeId]);
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

    #[Maho\Config\Route('/paypal/checkout/createOrder', methods: ['POST'])]
    public function createOrderAction(): void
    {
        $result = ['success' => false];

        try {
            if (!$this->_validateFormKey()) {
                Mage::throwException(Mage::helper('paypal')->__('Invalid form key.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if (!$quote->getId() || !$quote->getItemsCount()) {
                Mage::throwException(Mage::helper('paypal')->__('Cart is empty.'));
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
                $vaultToken = Mage::getModel('paypal/vault_token')->load($vaultTokenId);
                if ($vaultToken->getId() && $vaultToken->getIsActive()
                    && (int) $vaultToken->getCustomerId() === (int) $quote->getCustomerId()) {
                    $vaultPaypalTokenId = $vaultToken->getPaypalTokenId();
                    $vaultSourceType = $vaultToken->getPaymentSourceType();
                }
            }

            /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
            $builder = Mage::getModel('paypal/api_orderBuilder');

            $returnUrl = null;
            $cancelUrl = null;
            $shippingCallbackUrl = null;
            if ($methodCode === Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT) {
                $returnUrl = Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
                $cancelUrl = Mage::getUrl('checkout/cart', ['_secure' => true]);
                $shippingCallbackUrl = Mage::getUrl('paypal/checkout/shippingCallback', ['_secure' => true]);
            }

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
            $client = Mage::getModel('paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);
            $paypalOrder = $client->createOrder(['body' => $orderRequest]);

            $paypalOrderId = $paypalOrder['id'] ?? null;
            if (!$paypalOrderId) {
                Mage::log('PayPal createOrder response: ' . Mage::helper('core')->jsonEncode($paypalOrder), Mage::LOG_ERROR, 'paypal.log');
                $apiMessage = $paypalOrder['message'] ?? $paypalOrder['details'][0]['description'] ?? '';
                $errorMsg = $apiMessage
                    ? Mage::helper('paypal')->__('Failed to create PayPal order: %s', $apiMessage)
                    : Mage::helper('paypal')->__('Failed to create PayPal order.');
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
    #[Maho\Config\Route('/paypal/checkout/approveOrder', methods: ['POST'])]
    public function approveOrderAction(): void
    {
        $result = ['success' => false];

        try {
            if (!$this->_validateFormKey()) {
                Mage::throwException(Mage::helper('paypal')->__('Invalid form key.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();

            if (!$quote->getIsActive()) {
                Mage::throwException(Mage::helper('paypal')->__('This order has already been placed.'));
            }

            // Prefer the PayPal order ID sent by the frontend (the one the user actually
            // approved in the popup) over the quote's stored value, which can be stale
            // when the buyer retried and a newer createOrder overwrote it.
            $requestedPaypalOrderId = (string) $this->getRequest()->getParam('paypal_order_id');
            $storedPaypalOrderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');

            if ($requestedPaypalOrderId && preg_match('/^[A-Z0-9]+$/', $requestedPaypalOrderId)) {
                $paypalOrderId = $requestedPaypalOrderId;
                if ($storedPaypalOrderId && $storedPaypalOrderId !== $paypalOrderId) {
                    Mage::log(
                        sprintf(
                            'PayPal order ID mismatch – quote had "%s", request sent "%s" (quote %s). Using request value.',
                            $storedPaypalOrderId,
                            $paypalOrderId,
                            $quote->getId(),
                        ),
                        Mage::LOG_WARNING,
                        'paypal.log',
                    );
                    // Update the quote payment so downstream code sees the correct ID
                    $quote->getPayment()->setAdditionalInformation('paypal_order_id', $paypalOrderId);
                    $quote->getPayment()->setData('paypal_order_id', $paypalOrderId);
                    $quote->getPayment()->save();
                }
            } elseif ($storedPaypalOrderId) {
                $paypalOrderId = $storedPaypalOrderId;
            } else {
                Mage::throwException(Mage::helper('paypal')->__('Missing PayPal order ID.'));
            }
            $methodCode = $this->_validateMethodCode(
                $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT),
            );

            // Acquire lock to prevent concurrent order placement with webhook handlers
            $lock = Mage_Index_Model_Lock::getInstance();
            $lockName = 'paypal_order_' . $paypalOrderId;
            if (!$lock->setLock($lockName, file: true, block: true)) {
                Mage::throwException(Mage::helper('paypal')->__('Could not acquire order lock.'));
            }

            try {
                // Re-check quote is still active after acquiring lock (webhook may have placed the order)
                $quote = Mage::getModel('sales/quote')->load($quote->getId());
                if (!$quote->getIsActive()) {
                    /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $orderPayments */
                    $orderPayments = Mage::getResourceModel('sales/order_payment_collection');
                    $orderPayments->addFieldToFilter('paypal_order_id', $paypalOrderId);
                    $orderPayments->setPageSize(1);
                    $orderPayment = $orderPayments->getFirstItem();
                    if (!$orderPayment->getId()) {
                        Mage::throwException(Mage::helper('paypal')->__('Quote is no longer active and no matching order was found.'));
                    }
                    // Webhook placed the order while we waited for the lock — populate
                    // checkout session so the success page renders instead of redirecting to cart
                    $order = Mage::getModel('sales/order')->load($orderPayment->getParentId());
                    if (!$order->getId() || (int) $order->getQuoteId() !== (int) $quote->getId()) {
                        // Refuse to surface another quote's order via a replayed paypal_order_id
                        Mage::log(
                            sprintf(
                                'PayPal approveOrder: paypal_order_id %s resolves to order %s belonging to quote %s, not session quote %s.',
                                $paypalOrderId,
                                $order->getId() ?: 'n/a',
                                $order->getQuoteId() ?: 'n/a',
                                $quote->getId(),
                            ),
                            Mage::LOG_ERROR,
                            'paypal.log',
                        );
                        Mage::throwException(Mage::helper('paypal')->__('PayPal order does not belong to this cart.'));
                    }
                    $checkoutSession = Mage::getSingleton('checkout/session');
                    $checkoutSession->setLastQuoteId($quote->getId());
                    $checkoutSession->setLastSuccessQuoteId($quote->getId());
                    $checkoutSession->setLastOrderId($order->getId());
                    $checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $result['success'] = true;
                    $result['redirect_url'] = Mage::getUrl('checkout/onepage/success', ['_secure' => true]);
                } else {
                    /** @var Maho_Paypal_Model_Config $config */
                    $config = Mage::getModel('paypal/config');
                    $intent = $config->getNewPaymentAction($methodCode);

                    /** @var Maho_Paypal_Model_Api_Client $client */
                    $client = Mage::getModel('paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);

                    // Fetch current order status (include payment details in case it's already completed)
                    $paypalResult = $client->getOrder($paypalOrderId, 'purchase_units.payments');

                    // SECURITY: refuse to act on a PayPal order that wasn't created for this
                    // quote, or whose total/currency was tampered with. Without this check an
                    // attacker could replay any past completed paypal_order_id against a
                    // different cart and place an order paid by someone else's capture.
                    $this->_assertPaypalOrderMatchesQuote($paypalResult, $quote);
                    $this->_assertPaypalOrderNotAlreadyUsed($paypalOrderId);

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
                        Mage::throwException(Mage::helper('paypal')->__('PayPal order could not be approved. Status: %s', $status));
                    }

                    Mage::helper('paypal')->placeOrderFromPaypalResult($quote, $paypalResult, $methodCode, $intent);

                    $result['success'] = true;
                    $result['redirect_url'] = Mage::getUrl('checkout/onepage/success', ['_secure' => true]);
                }
            } finally {
                $lock->releaseLock($lockName, file: true);
            }
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
    #[Maho\Config\Route('/paypal/checkout/shippingCallback', methods: ['POST'])]
    public function shippingCallbackAction(): void
    {
        try {
            $body = $this->getRequest()->getRawBody();
            $data = Mage::helper('core')->jsonDecode($body);

            $paypalOrderId = $data['id'] ?? '';
            $shippingAddress = $data['shipping_address'] ?? [];
            $selectedOption = $data['shipping_option'] ?? null;

            // Find quote by PayPal order ID
            $quote = $this->_findQuoteByPaypalOrderId($paypalOrderId);
            if (!$quote || !$quote->getId() || $quote->isVirtual()) {
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
            $builder = Mage::getModel('paypal/api_orderBuilder');
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

            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        } catch (\Throwable $e) {
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
            Mage::throwException(Mage::helper('paypal')->__('Invalid payment method.'));
        }
        return $methodCode;
    }

    /**
     * Refuse to act on a PayPal order that wasn't created for this quote.
     * Bound via invoice_id (== quote's reservedOrderId) plus amount/currency
     * cross-check, so a leaked paypal_order_id from another cart cannot be
     * replayed against the current session.
     */
    protected function _assertPaypalOrderMatchesQuote(array $paypalResult, Mage_Sales_Model_Quote $quote): void
    {
        $purchaseUnit = $paypalResult['purchase_units'][0] ?? [];
        $paypalOrderId = (string) ($paypalResult['id'] ?? '');

        $expectedInvoiceId = (string) $quote->getReservedOrderId();
        $actualInvoiceId = (string) ($purchaseUnit['invoice_id'] ?? '');
        if ($expectedInvoiceId === '' || $actualInvoiceId !== $expectedInvoiceId) {
            Mage::log(
                sprintf(
                    'PayPal order %s invoice_id mismatch: expected "%s", got "%s" (quote %s).',
                    $paypalOrderId,
                    $expectedInvoiceId,
                    $actualInvoiceId,
                    $quote->getId(),
                ),
                Mage::LOG_ERROR,
                'paypal.log',
            );
            Mage::throwException(Mage::helper('paypal')->__('PayPal order does not belong to this cart.'));
        }

        $expectedCurrency = (string) $quote->getBaseCurrencyCode();
        $actualCurrency = (string) ($purchaseUnit['amount']['currency_code'] ?? '');
        if ($actualCurrency !== $expectedCurrency) {
            Mage::log(
                sprintf(
                    'PayPal order %s currency mismatch: expected "%s", got "%s" (quote %s).',
                    $paypalOrderId,
                    $expectedCurrency,
                    $actualCurrency,
                    $quote->getId(),
                ),
                Mage::LOG_ERROR,
                'paypal.log',
            );
            Mage::throwException(Mage::helper('paypal')->__('PayPal order currency does not match this cart.'));
        }

        $expectedAmount = (float) $quote->getBaseGrandTotal();
        $actualAmount = (float) ($purchaseUnit['amount']['value'] ?? 0);
        // 1-cent tolerance absorbs rounding drift between Maho and PayPal
        if (abs($expectedAmount - $actualAmount) > 0.01) {
            Mage::log(
                sprintf(
                    'PayPal order %s amount mismatch: expected %.2f, got %.2f (quote %s).',
                    $paypalOrderId,
                    $expectedAmount,
                    $actualAmount,
                    $quote->getId(),
                ),
                Mage::LOG_ERROR,
                'paypal.log',
            );
            Mage::throwException(Mage::helper('paypal')->__('PayPal order amount does not match this cart.'));
        }
    }

    /**
     * Reject a paypal_order_id already tied to an existing order payment.
     * In the active-quote branch this should never be true legitimately —
     * if it is, someone is trying to replay a completed PayPal order.
     */
    protected function _assertPaypalOrderNotAlreadyUsed(string $paypalOrderId): void
    {
        /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $payments */
        $payments = Mage::getResourceModel('sales/order_payment_collection');
        $payments->addFieldToFilter('paypal_order_id', $paypalOrderId);
        $payments->setPageSize(1);
        if ($payments->getFirstItem()->getId()) {
            Mage::log(
                sprintf('PayPal approveOrder: refused replay of paypal_order_id %s already tied to an order.', $paypalOrderId),
                Mage::LOG_ERROR,
                'paypal.log',
            );
            Mage::throwException(Mage::helper('paypal')->__('This PayPal order has already been used.'));
        }
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

}
