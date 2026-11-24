<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * @phpstan-import-type PaypalMoney from Maho_Paypal_Model_Api_Client
 * @phpstan-import-type PaypalOrder from Maho_Paypal_Model_Api_Client
 * @phpstan-import-type PaypalPurchaseUnit from Maho_Paypal_Model_Api_Client
 * @phpstan-import-type PaypalRelatedIds from Maho_Paypal_Model_Api_Client
 * @phpstan-import-type PaypalPaymentSource from Maho_Paypal_Model_Api_Client
 * @phpstan-type WebhookResource array{id?: string, status?: string, invoice_id?: string, amount?: PaypalMoney, purchase_units?: list<PaypalPurchaseUnit>, supplementary_data?: PaypalRelatedIds, status_details?: array{reason?: string, ...}, seller_payable_breakdown?: array{total_refunded_amount?: PaypalMoney, ...}, dispute_id?: string, reason?: string, disputed_transactions?: list<array{seller_transaction_id?: string, ...}>, dispute_outcome?: array{outcome_code?: string, ...}, customer?: array{id?: string, ...}, payment_source?: PaypalPaymentSource, ...}
 * @phpstan-type WebhookEvent array{id?: string, event_type?: string, event_version?: string, resource_type?: string, resource_version?: string, summary?: string, create_time?: string, resource?: WebhookResource, ...}
 */
abstract class Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    /**
     * @param WebhookEvent $payload
     */
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

    /**
     * @param WebhookEvent $payload
     */
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

    /**
     * Cancel $order through the order service, which holds the order lock. An order that cannot be canceled, such as
     * an order that an admin invoiced, stays as it is.
     */
    protected function _cancelOrder(Mage_Sales_Model_Order $order): void
    {
        try {
            Mage::getService('sales/order')->cancel($order);
        } catch (Mage_Core_Exception_Conflict $e) {
            $this->_log("Order {$order->getIncrementId()} not canceled: {$e->getMessage()}");
        }
    }

    protected function _findQuoteByPaypalOrderId(string $paypalOrderId): ?Mage_Sales_Model_Quote
    {
        if (!$paypalOrderId || !preg_match('/^[A-Z0-9]+$/', $paypalOrderId)) {
            return null;
        }

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
        /** @var Maho_Paypal_Helper_Data $helper */
        $helper = Mage::helper('paypal');
        return $helper->acquireOrderLock($paypalOrderId);
    }

    protected function _releaseLock(string $paypalOrderId): void
    {
        /** @var Maho_Paypal_Helper_Data $helper */
        $helper = Mage::helper('paypal');
        $helper->releaseOrderLock($paypalOrderId);
    }

    /**
     * @param PaypalOrder $paypalResult
     */
    protected function _placeOrderFromPaypalResult(
        Mage_Sales_Model_Quote $quote,
        array $paypalResult,
        string $methodCode,
        string $intent,
    ): void {
        /** @var Maho_Paypal_Helper_Data $helper */
        $helper = Mage::helper('paypal');
        $helper->placeOrderFromPaypalResult($quote, $paypalResult, $methodCode, $intent);
    }

    protected function _log(string $message): void
    {
        Mage::log($message, Mage::LOG_INFO, 'paypal.log');
    }
}
