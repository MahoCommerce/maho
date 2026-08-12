<?php

/**
 * Gives every consumed queue message its own OpenTelemetry trace.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * A worker outlives thousands of messages, so one span per process would hold
 * every child in memory and export nothing until the process died.
 */
final class TraceMessageListener implements EventSubscriberInterface
{
    private ?\Maho_OpenTelemetry_Model_Span $span = null;

    /**
     * @return array<class-string, string>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $tracer = \Mage::getTracer();
        if (!$tracer?->isEnabled()) {
            return;
        }

        // The class names the work; the payload is never recorded, a DTO carries customer data
        $message = $event->getEnvelope()->getMessage();
        $this->span = $tracer->startRootSpan('process ' . $message::class, [
            'messaging.system' => 'maho',
            'messaging.operation.name' => 'process',
            'messaging.destination.name' => $event->getReceiverName(),
            'maho.area' => 'queue',
        ], 'consumer');
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->span?->setStatus('ok');
        $this->finish();
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->span?->recordException($event->getThrowable());
        $this->span?->setStatus('error', $event->getThrowable()::class);
        $this->span?->setAttribute('maho.queue.will_retry', $event->willRetry());
        $this->finish();
    }

    /**
     * End the span and ship it now: the worker is idle between messages, so the
     * export costs nothing that a waiting customer pays for.
     */
    private function finish(): void
    {
        $this->span?->end();
        $this->span = null;
        \Mage::getTracer()?->flush();
    }
}
