<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

abstract class Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    abstract public function handle(array $payload): void;

    protected function _loadOrderByPaypalOrderId(string $paypalOrderId): ?Mage_Sales_Model_Order
    {
        /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $payments */
        $payments = Mage::getResourceModel('sales/order_payment_collection');
        $payments->addFieldToFilter('paypal_order_id', $paypalOrderId);
        $payments->setPageSize(1);

        $payment = $payments->getFirstItem();
        if (!$payment->getId()) {
            return null;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($payment->getParentId());
        return $order->getId() ? $order : null;
    }

    protected function _loadOrderByTransactionId(string $transactionId): ?Mage_Sales_Model_Order
    {
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->load($transactionId, 'txn_id');

        if (!$transaction->getId()) {
            return null;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($transaction->getOrderId());
        return $order->getId() ? $order : null;
    }

    protected function _loadOrderByInvoiceId(string $invoiceId): ?Mage_Sales_Model_Order
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($invoiceId);
        return $order->getId() ? $order : null;
    }

    protected function _findOrder(array $payload): ?Mage_Sales_Model_Order
    {
        $resource = $payload['resource'] ?? [];

        $invoiceId = $resource['invoice_id']
            ?? $resource['purchase_units'][0]['invoice_id']
            ?? null;
        if ($invoiceId) {
            $order = $this->_loadOrderByInvoiceId($invoiceId);
            if ($order) {
                return $order;
            }
        }

        $supplementaryData = $resource['supplementary_data']['related_ids'] ?? [];
        $paypalOrderId = $supplementaryData['order_id'] ?? null;
        if ($paypalOrderId) {
            $order = $this->_loadOrderByPaypalOrderId($paypalOrderId);
            if ($order) {
                return $order;
            }
        }

        $resourceId = $resource['id'] ?? null;
        if ($resourceId) {
            $order = $this->_loadOrderByTransactionId($resourceId);
            if ($order) {
                return $order;
            }
        }

        return null;
    }

    protected function _findQuoteByPaypalOrderId(string $paypalOrderId): ?Mage_Sales_Model_Quote
    {
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

        $storedOrderId = $quote->getPayment()->getAdditionalInformation('paypal_order_id');
        if ($storedOrderId !== $paypalOrderId) {
            return null;
        }

        return $quote;
    }

    /**
     * Acquire a file lock for the given PayPal order ID to prevent the webhook
     * and the JS controller from placing the same order concurrently.
     *
     * Returns true if the lock was acquired, false if another process holds it.
     */
    protected function _acquireLock(string $paypalOrderId): bool
    {
        $lock = Mage_Index_Model_Lock::getInstance();
        return $lock->setLock('paypal_order_' . $paypalOrderId, file: true, block: false);
    }

    protected function _releaseLock(string $paypalOrderId): void
    {
        $lock = Mage_Index_Model_Lock::getInstance();
        $lock->releaseLock('paypal_order_' . $paypalOrderId, file: true);
    }

    protected function _placeOrderFromPaypalResult(
        Mage_Sales_Model_Quote $quote,
        array $paypalResult,
        string $methodCode,
        string $intent,
    ): void {
        $payment = $quote->getPayment();
        $payment->setMethod($methodCode);
        $payment->setAdditionalInformation('paypal_order_id', $paypalResult['id']);
        $payment->setData('paypal_order_id', $paypalResult['id']);

        // Import transaction IDs
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

        // Import payer info
        $payer = $paypalResult['payer'] ?? [];
        if (!empty($payer['email_address'])) {
            $payment->setAdditionalInformation('payer_email', $payer['email_address']);
        }
        if (!empty($payer['payer_id'])) {
            $payment->setAdditionalInformation('payer_id', $payer['payer_id']);
        }

        $payment->save();

        // Import address from PayPal if quote has no billing address
        if (!$quote->getBillingAddress()->getFirstname()) {
            $this->_importPaypalAddress($paypalResult, $quote);
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
    }

    protected function _importPaypalAddress(array $paypalResult, Mage_Sales_Model_Quote $quote): void
    {
        $payer = $paypalResult['payer'] ?? [];
        $shipping = $paypalResult['purchase_units'][0]['shipping'] ?? [];
        $paypalAddress = $shipping['address'] ?? [];
        $paypalName = $shipping['name']['full_name'] ?? '';

        $email = $payer['email_address'] ?? '';
        if (!$email) {
            Mage::throwException(Mage::helper('maho_paypal')->__('PayPal did not return a payer email address.'));
        }

        // Use payer name when shipping name is unavailable (e.g. virtual orders)
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
            'telephone' => $payer['phone']['phone_number']['national_number'] ?? '0000000000',
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

    protected function _log(string $message): void
    {
        Mage::log($message, Mage::LOG_INFO, 'paypal.log');
    }
}
