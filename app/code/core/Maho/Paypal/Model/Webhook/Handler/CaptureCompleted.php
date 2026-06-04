<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CaptureCompleted extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';
        $capturedAmount = (float) ($resource['amount']['value'] ?? 0);

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureCompleted: order not found for capture {$captureId}");
            return;
        }

        // Skip if this capture was already registered during order placement
        $existing = Mage::getModel('sales/order_payment_transaction');
        $existing->load($captureId, 'txn_id');
        if ($existing->getId()) {
            $this->_log("CaptureCompleted: capture {$captureId} already registered for order {$order->getIncrementId()}, skipping");
            return;
        }

        $payment = $order->getPayment();

        $payment->setAdditionalInformation('paypal_capture_id', $captureId);
        $payment->setTransactionId($captureId);
        $payment->setIsTransactionClosed(true);

        // PayPal reports the captured amount in the order (quote/display) currency,
        // but registerCaptureNotification() expects base currency.
        $payment->registerCaptureNotification($this->_toBaseAmount($order, $capturedAmount));

        $invoice = $payment->getCreatedInvoice();

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($order);
        if ($invoice) {
            $transactionSave->addObject($invoice);
        }
        $transactionSave->save();

        $this->_log("CaptureCompleted: processed for order {$order->getIncrementId()}");
    }

    /**
     * Convert a PayPal-reported amount (order/display currency) to base currency.
     *
     * Avoids rate math on the realistic path: when PayPal captured the full order
     * amount we return the exact stored base grand total, so there is no rounding
     * drift. Only a genuine partial out-of-band capture falls back to the order's
     * base->order rate, where a sub-cent difference is unavoidable.
     */
    protected function _toBaseAmount(Mage_Sales_Model_Order $order, float $orderAmount): float
    {
        if ($order->getBaseCurrencyCode() === $order->getOrderCurrencyCode()) {
            return $orderAmount;
        }
        if (abs($orderAmount - (float) $order->getGrandTotal()) <= 0.01) {
            return (float) $order->getBaseGrandTotal();
        }
        $rate = (float) $order->getBaseToOrderRate() ?: 1.0;
        return round($orderAmount / $rate, 2);
    }
}
