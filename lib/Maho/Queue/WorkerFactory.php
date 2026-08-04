<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Worker;

/**
 * Builds a Messenger Worker wired with Maho's retry policy and failure
 * semantics. Time limits are passed to Worker::run() as options by the caller;
 * message and memory limits are listener-based.
 */
final class WorkerFactory
{
    /**
     * @param array{limit?: ?int, memoryLimit?: ?int, stopWhenIdle?: bool} $options
     */
    public static function create(array $options = []): Worker
    {
        $transportName = QueueManager::transportName();
        $transport = QueueManager::transport();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $dispatcher->addSubscriber(new DispatchPcntlSignalListener());

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            new ServiceLocator([$transportName => $transport]),
            new ServiceLocator([$transportName => new MultiplierRetryStrategy(
                (int) \Mage::getStoreConfig(QueueManager::XML_PATH_MAX_RETRIES),
                (int) \Mage::getStoreConfig(QueueManager::XML_PATH_RETRY_DELAY) * 1000,
                (float) \Mage::getStoreConfig(QueueManager::XML_PATH_RETRY_MULTIPLIER),
                (int) \Mage::getStoreConfig(QueueManager::XML_PATH_RETRY_MAX_DELAY) * 1000,
            )]),
        ));

        if ($transportName === QueueManager::TRANSPORT_REDIS) {
            // Final failures on Redis land in the DB table so the admin grid sees them.
            $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
                new ServiceLocator([$transportName => QueueManager::dbTransport()]),
            ));
        }

        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event): void {
            if (!$event->willRetry()) {
                \Mage::logException($event->getThrowable());
            }
        });

        $dispatcher->addSubscriber(new StopWorkerOnConfigChangeListener());

        if (isset($options['limit']) && $options['limit'] > 0) {
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($options['limit']));
        }
        if (isset($options['memoryLimit']) && $options['memoryLimit'] > 0) {
            $dispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener($options['memoryLimit']));
        }
        if ($options['stopWhenIdle'] ?? false) {
            $dispatcher->addSubscriber(new StopWorkerWhenIdleListener());
        }

        return new Worker([$transportName => $transport], QueueManager::bus(), $dispatcher);
    }
}
