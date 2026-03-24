<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_DisputeUpdated extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $disputeId = $resource['dispute_id'] ?? $resource['id'] ?? '';
        $status = $resource['status'] ?? 'UPDATED';

        $disputedTransaction = $resource['disputed_transactions'][0] ?? [];
        $order = null;

        if (!empty($disputedTransaction['seller_transaction_id'])) {
            $order = $this->_loadOrderByTransactionId($disputedTransaction['seller_transaction_id']);
        }

        if (!$order) {
            $order = $this->_findOrder($payload);
        }

        if (!$order) {
            $this->_log("DisputeUpdated: order not found for dispute {$disputeId}");
            return;
        }

        $order->addStatusHistoryComment("PayPal dispute updated ({$status}): {$disputeId}");
        $order->save();

        $this->_log("DisputeUpdated: processed for order {$order->getIncrementId()}");
    }
}
