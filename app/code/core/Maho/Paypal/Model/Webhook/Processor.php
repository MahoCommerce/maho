<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Processor
{
    protected const HANDLER_MAP = [
        'PAYMENT.CAPTURE.COMPLETED'      => 'maho_paypal/webhook_handler_captureCompleted',
        'PAYMENT.CAPTURE.PENDING'        => 'maho_paypal/webhook_handler_capturePending',
        'PAYMENT.CAPTURE.DECLINED'       => 'maho_paypal/webhook_handler_captureDeclined',
        'PAYMENT.CAPTURE.REFUNDED'       => 'maho_paypal/webhook_handler_captureRefunded',
        'PAYMENT.CAPTURE.REVERSED'       => 'maho_paypal/webhook_handler_captureReversed',
        'PAYMENT.AUTHORIZATION.CREATED'  => 'maho_paypal/webhook_handler_authorizationCreated',
        'PAYMENT.AUTHORIZATION.VOIDED'   => 'maho_paypal/webhook_handler_authorizationVoided',
        'CUSTOMER.DISPUTE.CREATED'       => 'maho_paypal/webhook_handler_disputeCreated',
        'CUSTOMER.DISPUTE.UPDATED'       => 'maho_paypal/webhook_handler_disputeUpdated',
        'CUSTOMER.DISPUTE.RESOLVED'      => 'maho_paypal/webhook_handler_disputeResolved',
        'VAULT.PAYMENT-TOKEN.CREATED'    => 'maho_paypal/webhook_handler_vaultTokenCreated',
        'VAULT.PAYMENT-TOKEN.DELETED'    => 'maho_paypal/webhook_handler_vaultTokenDeleted',
    ];

    public function process(array $payload): void
    {
        $eventId = $payload['id'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if (!$eventId || !$eventType) {
            Mage::log('Invalid webhook payload: missing id or event_type', Mage::LOG_WARNING, 'paypal.log');
            return;
        }

        if ($this->_isDuplicate($eventId)) {
            Mage::log("Duplicate webhook event: {$eventId}", Mage::LOG_INFO, 'paypal.log');
            return;
        }

        $event = $this->_createEventRecord($payload);

        try {
            $handlerClass = self::HANDLER_MAP[$eventType] ?? null;
            if (!$handlerClass) {
                Mage::log("No handler for webhook event type: {$eventType}", Mage::LOG_INFO, 'paypal.log');
                $event->setStatus('skipped')->save();
                return;
            }

            /** @var Maho_Paypal_Model_Webhook_Handler_AbstractHandler $handler */
            $handler = Mage::getModel($handlerClass);
            $handler->handle($payload);

            $event->setStatus('processed');
            $event->setProcessedAt(Mage_Core_Model_Locale::now());
            $event->save();
        } catch (\Throwable $e) {
            $event->setStatus('error');
            $event->setErrorMessage($e->getMessage());
            $event->setProcessedAt(Mage_Core_Model_Locale::now());
            $event->save();
            throw $e;
        }
    }

    /**
     * Process a webhook payload without signature verification (for CLI simulate)
     */
    public function processUnsafe(array $payload): void
    {
        $this->process($payload);
    }

    protected function _isDuplicate(string $paypalEventId): bool
    {
        /** @var Maho_Paypal_Model_Resource_Webhook_Event_Collection $collection */
        $collection = Mage::getResourceModel('maho_paypal/webhook_event_collection');
        $collection->addFieldToFilter('paypal_event_id', $paypalEventId);
        return $collection->getSize() > 0;
    }

    protected function _createEventRecord(array $payload): Maho_Paypal_Model_Webhook_Event
    {
        /** @var Maho_Paypal_Model_Webhook_Event $event */
        $event = Mage::getModel('maho_paypal/webhook_event');
        $event->setData([
            'paypal_event_id' => $payload['id'],
            'event_type'      => $payload['event_type'],
            'resource_type'   => $payload['resource_type'] ?? null,
            'resource_id'     => $payload['resource']['id'] ?? null,
            'summary'         => $payload['summary'] ?? null,
            'status'          => 'processing',
            'payload'         => Mage::helper('core')->jsonEncode($payload),
        ]);
        $event->save();
        return $event;
    }
}
