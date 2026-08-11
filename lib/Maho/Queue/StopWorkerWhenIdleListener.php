<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Stops the worker once polls stop finding messages, for bounded runs and for
 * on-demand pools that free their process between bursts. Messenger has no
 * built-in stop-on-idle listener.
 *
 * On-demand pools want a grace period: exiting on the first empty poll makes a
 * job queued seconds later wait a whole cron tick for a replacement.
 */
final class StopWorkerWhenIdleListener implements EventSubscriberInterface
{
    private ?int $idleSince = null;

    public function __construct(
        private readonly int $idleTimeoutSeconds = 0,
    ) {}

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            $this->idleSince = null;
            return;
        }

        $this->idleSince ??= time();
        if (time() - $this->idleSince >= $this->idleTimeoutSeconds) {
            $event->getWorker()->stop();
        }
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }
}
