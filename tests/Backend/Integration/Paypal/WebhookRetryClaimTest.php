<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function paypalRetryProcessor(): Maho_Paypal_Model_Webhook_Processor
{
    return new class extends Maho_Paypal_Model_Webhook_Processor {
        public function claim(array $payload): ?Maho_Paypal_Model_Webhook_Event
        {
            return $this->_createEventRecord($payload);
        }
    };
}

it('reclaims a failed webhook event but not a completed duplicate', function (): void {
    $eventId = 'PAYPAL-RETRY-' . bin2hex(random_bytes(6));
    $payload = [
        'id' => $eventId,
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource_type' => 'capture',
        'resource' => ['id' => 'CAPTURE-1'],
    ];
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $processor = paypalRetryProcessor();

    try {
        $first = $processor->claim($payload);
        expect($first)->not->toBeNull();
        $first->setStatus('error')->save();

        $retry = $processor->claim($payload);
        expect($retry)->not->toBeNull()
            ->and((int) $retry->getId())->toBe((int) $first->getId())
            ->and($retry->getStatus())->toBe('processing')
            ->and($processor->claim($payload))->toBeNull();

        $retry->setStatus('processed')->save();
        expect($processor->claim($payload))->toBeNull();
    } finally {
        $write->delete('paypal_webhook_event', ['paypal_event_id = ?' => $eventId]);
    }
});
