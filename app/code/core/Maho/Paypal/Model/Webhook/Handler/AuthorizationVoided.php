<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_AuthorizationVoided extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $authId = $resource['id'] ?? '';

        $order = $this->_findOrder($payload);
        if (!$order) {
            $this->_log("AuthorizationVoided: order not found for authorization {$authId}");
            return;
        }

        if ($order->canCancel()) {
            $order->cancel();
        }

        $order->addStatusHistoryComment("PayPal authorization voided: {$authId}");
        $order->save();

        $this->_log("AuthorizationVoided: processed for order {$order->getIncrementId()}");
    }
}
