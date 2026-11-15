<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CaptureDeclined extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureDeclined: order not found for capture {$captureId}");
            return;
        }

        $this->_cancelOrder($order);

        $reason = $resource['status_details']['reason'] ?? 'DECLINED';
        $order->addStatusHistoryComment("PayPal capture declined ({$reason}): {$captureId}");
        $order->save();

        $this->_log("CaptureDeclined: processed for order {$order->getIncrementId()}");
    }
}
