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
 * Stops the worker when store configuration changes, so it restarts with
 * fresh settings instead of running on the config it booted with (a stale
 * worker would e.g. keep sending email through an old SMTP transport). The
 * watchdog respawns a stopped worker within a minute.
 *
 * The signal is a periodic checksum of core_config_data, which catches every
 * write path (admin saves, config:set, direct DB changes); the config cache
 * is not a usable signal because reinit overwrites entries instead of
 * cleaning the tag. The table is small, so the periodic scan is cheap.
 */
final class StopWorkerOnConfigChangeListener implements EventSubscriberInterface
{
    private string $checksum;
    private float $lastCheckedAt;

    public function __construct(
        private readonly int $checkIntervalSeconds = 15,
    ) {
        $this->checksum = $this->computeChecksum();
        $this->lastCheckedAt = microtime(true);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (microtime(true) - $this->lastCheckedAt < $this->checkIntervalSeconds) {
            return;
        }
        $this->lastCheckedAt = microtime(true);

        if ($this->computeChecksum() !== $this->checksum) {
            \Mage::log('Configuration changed: stopping the queue worker so a fresh one picks up the new settings', \Mage::LOG_NOTICE);
            $event->getWorker()->stop();
        }
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }

    private function computeChecksum(): string
    {
        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from(\Mage::getSingleton('core/resource')->getTableName('core/config_data'), ['scope', 'scope_id', 'path', 'value'])
                ->order(['scope', 'scope_id', 'path']),
        );

        return md5(serialize($rows));
    }
}
