<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Failsafe handler for CHECKOUT.ORDER.COMPLETED webhook.
 *
 * Fires when the PayPal order reaches COMPLETED status (all captures done).
 * If a Mage order already exists, this is a no-op. Otherwise it acts as a
 * secondary failsafe to place the order using the completed PayPal data.
 */
class Maho_Paypal_Model_Webhook_Handler_OrderCompleted extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $paypalOrderId = $resource['id'] ?? '';

        if (!$paypalOrderId) {
            $this->_log('OrderCompleted: missing order ID in payload');
            return;
        }

        // If a Mage order already exists, nothing to do
        $existingOrder = $this->_loadOrderByPaypalOrderId($paypalOrderId);
        if ($existingOrder) {
            $this->_log("OrderCompleted: Mage order {$existingOrder->getIncrementId()} already exists, skipping");
            return;
        }

        $quote = $this->_findQuoteByPaypalOrderId($paypalOrderId);
        if (!$quote) {
            $this->_log("OrderCompleted: no active quote found for PayPal order {$paypalOrderId}");
            return;
        }

        $methodCode = $quote->getPayment()->getMethod()
            ?: Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT;

        /** @var Maho_Paypal_Model_Config $config */
        $config = Mage::getModel('paypal/config');
        $intent = $config->getNewPaymentAction($methodCode, (int) $quote->getStoreId());

        // Order is already COMPLETED, no need to capture/authorize again
        $this->_placeOrderFromPaypalResult($quote, $resource, $methodCode, $intent);
        $this->_log("OrderCompleted: placed order from webhook for PayPal order {$paypalOrderId}");
    }
}
