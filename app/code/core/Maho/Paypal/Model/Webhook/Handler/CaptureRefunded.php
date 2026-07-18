<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_CaptureRefunded extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("CaptureRefunded: order not found for capture {$captureId}");
            return;
        }

        $refundAmount = $resource['seller_payable_breakdown']['total_refunded_amount']['value'] ?? null;
        $comment = "PayPal capture refunded: {$captureId}";
        if ($refundAmount) {
            $comment .= " (amount: {$refundAmount})";
        }

        $order->addStatusHistoryComment($comment);
        $order->save();

        $this->_log("CaptureRefunded: processed for order {$order->getIncrementId()}");
    }
}
