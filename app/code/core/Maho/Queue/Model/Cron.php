<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

class Maho_Queue_Model_Cron
{
    /**
     * Held by the exclusive worker for its whole life; deliberately a
     * machine-local kernel flock even when the db lock backend is configured,
     * so every frontend server runs one worker of its own (parallel
     * consumption is safe, the transport claim is atomic) and the flock
     * disappears the moment the process dies, doubling as the liveness probe.
     */
    public const WORKER_LOCK = 'queue.worker';

    /** Worker recycling cadence: picks up deployed code and frees memory. */
    public const WORKER_TIME_LIMIT = 3600;
    public const WORKER_MEMORY_LIMIT = '256M';

    private const SPAWN_WAIT_ATTEMPTS = 10;
    private const SPAWN_WAIT_MICROSECONDS = 500_000;

    /**
     * Watchdog: when no worker holds the lock, start a detached
     * `queue:work --exclusive` (respawned within a minute of any death,
     * recycled hourly via its time limit).
     */
    #[Maho\Config\CronJob('queue_process', schedule: '* * * * *')]
    public function process(): void
    {
        if (Mage::getSingleton('core/lock')->isHeld(self::WORKER_LOCK, machineLocal: true)) {
            return;
        }

        if (!function_exists('exec')) {
            Mage::log('Cannot start the queue worker: exec() is not available on this host', Mage::LOG_ERROR);
            return;
        }

        $this->spawnWorker();
    }

    #[Maho\Config\CronJob('queue_clean_up', schedule: '0 2 * * *')]
    public function cleanup(): void
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = QueueManager::tableName();

        $completedDays = (int) Mage::getStoreConfig(QueueManager::XML_PATH_COMPLETED_RETENTION);
        if ($completedDays > 0) {
            $adapter->delete($table, [
                'status = ?' => DbTransport::STATUS_COMPLETED,
                'processed_at < ?' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $completedDays * 86400),
            ]);
        }

        $failedDays = (int) Mage::getStoreConfig(QueueManager::XML_PATH_FAILED_RETENTION);
        if ($failedDays > 0) {
            $adapter->delete($table, [
                'status = ?' => DbTransport::STATUS_FAILED,
                'processed_at < ?' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $failedDays * 86400),
            ]);
        }
    }

    private function spawnWorker(): void
    {
        exec(sprintf(
            'nohup %s %s queue:work --exclusive --time-limit=%d --memory-limit=%s >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(Mage::getBaseDir() . '/maho'),
            self::WORKER_TIME_LIMIT,
            escapeshellarg(self::WORKER_MEMORY_LIMIT),
            escapeshellarg(Mage::getBaseDir('var') . '/log/queue-worker.log'),
        ));

        $lock = Mage::getSingleton('core/lock');
        for ($attempt = 0; $attempt < self::SPAWN_WAIT_ATTEMPTS; $attempt++) {
            usleep(self::SPAWN_WAIT_MICROSECONDS);
            if ($lock->isHeld(self::WORKER_LOCK, machineLocal: true)) {
                return;
            }
        }

        Mage::log('Queue worker did not start after spawning; check var/log/queue-worker.log', Mage::LOG_ERROR);
    }
}
