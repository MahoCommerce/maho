<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_DisputeCreated extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $disputeId = $resource['dispute_id'] ?? $resource['id'] ?? '';
        $reason = $resource['reason'] ?? 'UNKNOWN';

        $disputedTransaction = $resource['disputed_transactions'][0] ?? [];
        $order = null;

        if (!empty($disputedTransaction['seller_transaction_id'])) {
            $order = $this->_loadOrderByTransactionId($disputedTransaction['seller_transaction_id']);
        }

        if (!$order) {
            $order = $this->_findOrder($payload);
        }

        if (!$order) {
            $this->_log("DisputeCreated: order not found for dispute {$disputeId}");
            return;
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('dispute_id', $disputeId);
        $payment->setAdditionalInformation('dispute_reason', $reason);
        $payment->setIsTransactionPending(true);
        $payment->setIsFraudDetected(true);
        $payment->save();

        $order->addStatusHistoryComment(
            "PayPal dispute opened ({$reason}): {$disputeId}",
        );
        $order->save();

        $this->_log("DisputeCreated: processed for order {$order->getIncrementId()}");
    }
}
