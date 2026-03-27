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
 * Failsafe handler for CHECKOUT.ORDER.APPROVED webhook.
 *
 * If the buyer approved payment in the PayPal popup but the browser died
 * before the JS onApprove callback could call approveOrderAction, this
 * handler captures/authorizes the PayPal order and places the Mage order.
 */
class Maho_Paypal_Model_Webhook_Handler_OrderApproved extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $paypalOrderId = $resource['id'] ?? '';

        if (!$paypalOrderId) {
            $this->_log('OrderApproved: missing order ID in payload');
            return;
        }

        if (!$this->_acquireLock($paypalOrderId)) {
            $this->_log("OrderApproved: could not acquire lock for {$paypalOrderId}, another process is handling it");
            return;
        }

        try {
            // If a Mage order already exists, the JS flow succeeded
            $existingOrder = $this->_loadOrderByPaypalOrderId($paypalOrderId);
            if ($existingOrder) {
                $this->_log("OrderApproved: Mage order {$existingOrder->getIncrementId()} already exists, skipping");
                return;
            }

            $quote = $this->_findQuoteByPaypalOrderId($paypalOrderId);
            if (!$quote) {
                $this->_log("OrderApproved: no active quote found for PayPal order {$paypalOrderId}");
                return;
            }

            $methodCode = $quote->getPayment()->getMethod()
                ?: Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT;

            /** @var Maho_Paypal_Model_Config $config */
            $config = Mage::getModel('paypal/config');
            $intent = $config->getNewPaymentAction($methodCode, (int) $quote->getStoreId());

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);

            if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
                $paypalResult = $client->captureOrder($paypalOrderId);
            } else {
                $paypalResult = $client->authorizeOrder($paypalOrderId);
            }

            $status = $paypalResult['status'] ?? '';
            if (!in_array($status, ['COMPLETED', 'APPROVED'])) {
                $this->_log("OrderApproved: unexpected status '{$status}' after capture/authorize for {$paypalOrderId}");
                return;
            }

            $this->_placeOrderFromPaypalResult($quote, $paypalResult, $methodCode, $intent);
            $this->_log("OrderApproved: placed order from webhook for PayPal order {$paypalOrderId}");
        } finally {
            $this->_releaseLock($paypalOrderId);
        }
    }
}
