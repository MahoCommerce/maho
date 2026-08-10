<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\Pool;
use Maho\Queue\PoolRegistry;
use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

class Maho_Queue_Model_Cron
{
    /**
     * Watchdog: start a detached `queue:work --exclusive` for every configured
     * pool with no live worker, so each latency tier gets a process of its own
     * and a slow handler can never sit in front of a fast one.
     */
    #[Maho\Config\CronJob('queue_process', schedule: '* * * * *')]
    public function process(): void
    {
        $pending = $this->workersToSpawn();
        if ($pending === []) {
            return;
        }

        if (!function_exists('exec')) {
            Mage::log('Cannot start the queue worker: exec() is not available on this host', Mage::LOG_ERROR);
            return;
        }

        foreach ($pending as [$pool, $index]) {
            $this->spawnWorker($pool, $index);
        }
    }

    /**
     * Split out from process() so the decision is testable without spawning.
     *
     * @return list<array{Pool, int}>
     */
    public function workersToSpawn(): array
    {
        $lock = Mage::getSingleton('core/lock');

        // A hand-run `queue:work --exclusive` consumes every queue, so it stands
        // in for the whole roster and the watchdog keeps out of its way.
        if ($lock->isHeld(Pool::LOCK_PREFIX, machineLocal: true)) {
            return [];
        }

        $spawn = [];

        foreach (PoolRegistry::all() as $pool) {
            $due = null;
            $idle = null;
            $live = 0;
            $free = [];
            for ($index = 0; $index < $pool->count; $index++) {
                if ($lock->isHeld($pool->lockName($index), machineLocal: true)) {
                    $live++;
                } else {
                    $free[] = $index;
                }
            }

            foreach ($free as $index) {
                // One process per due message. A busy worker cannot take one, and holds
                // exactly one claim, so live claims come off the roster.
                if ($pool->isOnDemand()) {
                    $due ??= $this->dueWorkCount($pool);
                    $idle ??= $live === 0 ? 0 : max(0, $live - $this->busyWorkerCount($pool));
                    if ($idle >= $due) {
                        break;
                    }
                    $idle++;
                }
                $spawn[] = [$pool, $index];
            }
        }

        return $spawn;
    }

    private function dueWorkCount(Pool $pool): int
    {
        return QueueManager::workerTransport($pool)->countDue($pool->queues);
    }

    private function busyWorkerCount(Pool $pool): int
    {
        return QueueManager::workerTransport($pool)->countClaimed($pool->queues);
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

    private function spawnWorker(Pool $pool, int $index): void
    {
        exec(sprintf(
            'nohup %s %s queue:work --exclusive --pool=%s --index=%d >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(Mage::getBaseDir() . '/maho'),
            escapeshellarg($pool->name),
            $index,
            escapeshellarg(Mage::getBaseDir('var') . '/log/queue-worker.log'),
        ));
    }
}
