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

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureCompleted: order not found for capture {$captureId}");
            return;
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('paypal_capture_id', $captureId);

        $invoice = null;
        if ($order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($captureId);
            $invoice->register();
            $invoice->pay();
        }

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, "PayPal capture completed: {$captureId}");

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($order);
        if ($invoice) {
            $transactionSave->addObject($invoice);
        }
        $transactionSave->save();

        $this->_log("CaptureCompleted: processed for order {$order->getIncrementId()}");
    }
}
