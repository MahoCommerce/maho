<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CaptureReversed extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureReversed: order not found for capture {$captureId}");
            return;
        }

        $reason = $resource['status_details']['reason'] ?? 'REVERSED';
        $order->addStatusHistoryComment("PayPal capture reversed ({$reason}): {$captureId}");
        $order->save();

        $this->_log("CaptureReversed: processed for order {$order->getIncrementId()}");
    }
}
