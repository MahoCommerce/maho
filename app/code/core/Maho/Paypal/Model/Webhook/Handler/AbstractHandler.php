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
        $paypalOrderId = $supplementaryData['order_id'] ?? $resource['id'] ?? null;
        if ($paypalOrderId) {
            $order = $this->_loadOrderByPaypalOrderId($paypalOrderId);
            if ($order) {
                return $order;
            }
        }

        return null;
    }

    protected function _log(string $message): void
    {
        Mage::log($message, Mage::LOG_INFO, 'paypal.log');
    }
}
