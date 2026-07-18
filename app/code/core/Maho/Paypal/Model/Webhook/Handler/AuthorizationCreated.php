<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_AuthorizationCreated extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $authId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("AuthorizationCreated: order not found for authorization {$authId}");
            return;
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('paypal_authorization_id', $authId);
        $payment->save();

        $order->addStatusHistoryComment("PayPal authorization created: {$authId}");
        $order->save();

        $this->_log("AuthorizationCreated: processed for order {$order->getIncrementId()}");
    }
}
