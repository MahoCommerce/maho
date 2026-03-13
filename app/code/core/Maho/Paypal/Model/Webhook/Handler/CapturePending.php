<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CapturePending extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CapturePending: order not found for capture {$captureId}");
            return;
        }

        $reason = $resource['status_details']['reason'] ?? 'PENDING';
        $order->addStatusHistoryComment("PayPal capture pending ({$reason}): {$captureId}");
        $order->save();

        $this->_log("CapturePending: processed for order {$order->getIncrementId()}");
    }
}
