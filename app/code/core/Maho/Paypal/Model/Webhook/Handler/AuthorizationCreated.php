<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
