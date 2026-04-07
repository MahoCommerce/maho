<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Maho_Paypal';

    public function importPaypalAddress(array $paypalResult, Mage_Sales_Model_Quote $quote): void
    {
        $payer = $paypalResult['payer'] ?? [];
        $shipping = $paypalResult['purchase_units'][0]['shipping'] ?? [];
        $paypalAddress = $shipping['address'] ?? [];
        $paypalName = $shipping['name']['full_name'] ?? '';

        $email = $payer['email_address'] ?? '';
        if (!$email) {
            Mage::throwException($this->__('PayPal did not return a payer email address.'));
        }

        if (!$paypalName) {
            $payerName = $payer['name'] ?? [];
            $firstname = $payerName['given_name'] ?? '';
            $lastname = $payerName['surname'] ?? $firstname;
        } else {
            $nameParts = explode(' ', $paypalName, 2);
            $firstname = $nameParts[0] ?? '';
            $lastname = $nameParts[1] ?? $firstname;
        }

        $quote->setCustomerEmail($email);
        $quote->setCustomerFirstname($firstname);
        $quote->setCustomerLastname($lastname);

        $billingData = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'telephone' => $payer['phone']['phone_number']['national_number']
                ?? $quote->getBillingAddress()->getTelephone()
                ?: '0000000000',
        ];

        if ($paypalAddress) {
            $billingData['street'] = implode("\n", array_filter([
                $paypalAddress['address_line_1'] ?? '',
                $paypalAddress['address_line_2'] ?? '',
            ]));
            $billingData['city'] = $paypalAddress['admin_area_2'] ?? '';
            $billingData['region'] = $paypalAddress['admin_area_1'] ?? '';
            $billingData['postcode'] = $paypalAddress['postal_code'] ?? '';
            $billingData['country_id'] = $paypalAddress['country_code'] ?? '';
        }

        $billing = $quote->getBillingAddress();
        $billing->addData($billingData);
        $billing->setPaymentMethod($quote->getPayment()->getMethod());
        $billing->save();

        if (!$quote->isVirtual() && $paypalAddress) {
            $shippingAddr = $quote->getShippingAddress();
            $previousMethod = $shippingAddr->getShippingMethod();
            $shippingAddr->addData($billingData);
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
    }

    public function placeOrderFromPaypalResult(
        Mage_Sales_Model_Quote $quote,
        array $paypalResult,
        string $methodCode,
        string $intent,
    ): void {
        $payment = $quote->getPayment();
        $payment->setMethod($methodCode);
        $payment->setAdditionalInformation('paypal_order_id', $paypalResult['id']);
        $payment->setData('paypal_order_id', $paypalResult['id']);

        $purchaseUnit = $paypalResult['purchase_units'][0] ?? [];
        $paymentsData = $purchaseUnit['payments'] ?? [];

        if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
            $captureId = $paymentsData['captures'][0]['id'] ?? null;
            if ($captureId) {
                $payment->setAdditionalInformation('paypal_capture_id', $captureId);
            }
        }

        $authId = $paymentsData['authorizations'][0]['id'] ?? null;
        if ($authId) {
            $payment->setAdditionalInformation('paypal_authorization_id', $authId);
        }

        $payer = $paypalResult['payer'] ?? [];
        if (!empty($payer['email_address'])) {
            $payment->setAdditionalInformation('payer_email', $payer['email_address']);
        }
        if (!empty($payer['payer_id'])) {
            $payment->setAdditionalInformation('payer_id', $payer['payer_id']);
        }

        $payment->save();

        $this->importPaypalAddress($paypalResult, $quote);

        $this->saveVaultToken($paypalResult, $quote);

        // Ensure correct checkout method for sessionless contexts (webhooks)
        if ($quote->getCustomerId() && !$quote->getData('checkout_method')) {
            $quote->setData('checkout_method', Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            // Load and attach the customer so _prepareCustomerQuote() doesn't
            // overwrite the quote's customer with an empty session object
            $customer = Mage::getModel('customer/customer')->load($quote->getCustomerId());
            if ($customer->getId()) {
                $quote->setCustomer($customer);
            }
        }

        $quote->collectTotals();

        try {
            /** @var Mage_Checkout_Model_Type_Onepage $onepage */
            $onepage = Mage::getSingleton('checkout/type_onepage');
            $onepage->setQuote($quote);
            $onepage->saveOrder();
        } catch (\Throwable $e) {
            Mage::log(
                sprintf(
                    'CRITICAL: PayPal order %s was captured/authorized but Mage order placement failed: %s',
                    $paypalResult['id'],
                    $e->getMessage(),
                ),
                Mage::LOG_ERROR,
                'paypal.log',
            );
            throw $e;
        }

        $quote->setIsActive(0);
        $quote->save();

        // Register capture on the order immediately so payment data is complete
        // before any external system (e.g. dispatch) pulls it, instead of
        // relying on the CaptureCompleted webhook which may arrive seconds later.
        if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
            $capture = $paymentsData['captures'][0] ?? [];
            $captureId = $capture['id'] ?? '';
            $captureAmount = (float) ($capture['amount']['value'] ?? 0);

            if ($captureId && $captureAmount) {
                try {
                    $order = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
                    if ($order->getId()) {
                        $orderPayment = $order->getPayment();
                        $orderPayment->setAdditionalInformation('paypal_capture_id', $captureId);
                        $orderPayment->setTransactionId($captureId);
                        $orderPayment->setIsTransactionClosed(true);
                        $orderPayment->registerCaptureNotification($captureAmount);

                        $invoice = $orderPayment->getCreatedInvoice();
                        $transactionSave = Mage::getModel('core/resource_transaction')
                            ->addObject($order);
                        if ($invoice) {
                            $transactionSave->addObject($invoice);
                        }
                        $transactionSave->save();
                    }
                } catch (\Throwable $e) {
                    Mage::log(
                        sprintf('Inline capture registration failed for PayPal order %s: %s', $paypalResult['id'], $e->getMessage()),
                        Mage::LOG_ERROR,
                        'paypal.log',
                    );
                }
            }
        }
    }

    public function saveVaultToken(array $paypalResult, Mage_Sales_Model_Quote $quote): void
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
        $existing->addPaypalTokenFilter($paypalTokenId);
        if ($existing->getSize() > 0) {
            return;
        }

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
