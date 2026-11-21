<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CaptureCompleted extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';
        $amount = $resource['amount'] ?? [];
        $value = $amount['value'] ?? null;
        if ($captureId === '' || ($resource['status'] ?? 'COMPLETED') !== 'COMPLETED'
            || !is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0
        ) {
            return;
        }

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureCompleted: order not found for capture {$captureId}");
            return;
        }

        $orderId = (int) $order->getId();
        Mage::getService('sales/order')->withOrderLock($orderId, function () use ($orderId, $captureId, $amount): void {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId() && ($amount['currency_code'] ?? null) === $order->getBaseCurrencyCode()) {
                $this->registerCapture($order, $captureId, $amount);
            }
        });
    }

    private function registerCapture(Mage_Sales_Model_Order $order, string $captureId, array $amount): void
    {
        $payment = $order->getPayment();
        $invoiceForCapture = null;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getTransactionId() === $captureId) {
                $invoiceForCapture = $invoice;
                break;
            }
            if ((int) $invoice->getState() === Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
                $invoiceForCapture ??= $invoice;
            }
        }
        $invoiceCaptureId = $invoiceForCapture?->getTransactionId();
        if ($invoiceCaptureId !== $captureId && $payment->getTransaction($captureId)) {
            return;
        }
        if ($invoiceForCapture && (
            (int) $invoiceForCapture->getState() !== Mage_Sales_Model_Order_Invoice::STATE_OPEN
            || ($invoiceCaptureId && $invoiceCaptureId !== $captureId)
            || !Mage::helper('paypal')->isFullInvoiceCapture($amount, $invoiceForCapture)
        )) {
            return;
        }

        $payment->setAdditionalInformation('paypal_capture_id', $captureId);
        $payment->setTransactionId($captureId);
        $payment->setIsTransactionClosed(true);
        $payment->registerCaptureNotification((float) $amount['value']);

        $invoice = $payment->getCreatedInvoice();

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($order);
        if ($invoice) {
            $transactionSave->addObject($invoice);
        }
        $transactionSave->save();

        $this->_log("CaptureCompleted: processed for order {$order->getIncrementId()}");
    }
}
