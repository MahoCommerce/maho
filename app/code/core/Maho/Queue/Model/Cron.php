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
    private const SPAWN_WAIT_ATTEMPTS = 10;
    private const SPAWN_WAIT_MICROSECONDS = 500_000;

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
        $spawn = [];

        foreach (PoolRegistry::all() as $pool) {
            $hasWork = null;
            for ($index = 0; $index < $pool->count; $index++) {
                if ($lock->isHeld($pool->lockName($index), machineLocal: true)) {
                    continue;
                }
                if ($pool->isOnDemand()) {
                    $hasWork ??= $this->hasDueWork($pool);
                    if (!$hasWork) {
                        break;
                    }
                }
                $spawn[] = [$pool, $index];
            }
        }

        return $spawn;
    }

    private function hasDueWork(Pool $pool): bool
    {
        $transport = QueueManager::workerTransport($pool);

        // Redis cannot be probed per queue; spawn and let the worker idle out.
        return !$transport instanceof DbTransport || $transport->countDue($pool->queues) > 0;
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

        $lock = Mage::getSingleton('core/lock');
        for ($attempt = 0; $attempt < self::SPAWN_WAIT_ATTEMPTS; $attempt++) {
            usleep(self::SPAWN_WAIT_MICROSECONDS);
            if ($lock->isHeld($pool->lockName($index), machineLocal: true)) {
                return;
            }
        }

        // An on-demand worker may have drained its queue and exited inside the wait window.
        Mage::log(
            sprintf('Queue worker for pool "%s" did not start after spawning; check var/log/queue-worker.log', $pool->name),
            $pool->isOnDemand() ? Mage::LOG_NOTICE : Mage::LOG_ERROR,
        );
    }
}
