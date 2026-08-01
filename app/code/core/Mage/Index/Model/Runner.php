<?php

/**
 * Runs a batch of index processes, reporting each one as it goes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

class Mage_Index_Model_Runner
{
    /**
     * Name of the lock this runner holds for the whole of one step. A process releases its own lock
     * partway through reindexAll() and keeps working, so that one cannot answer "is the worker
     * still alive", which is what the poller needs. Scoped to the run, otherwise a later run
     * rebuilding the same index would keep a dead run's dialog spinning.
     */
    public static function lockName(string $token, string $code): string
    {
        return 'index_run_' . $token . '_' . $code;
    }

    /**
     * Resolve process ids into the ordered list of processes that will actually run.
     *
     * Dependencies are expanded up front, so the caller can show every index that is about to be
     * rebuilt. Left to itself, reindexEverything() reindexes a dependency silently from inside
     * another process's call and the caller never learns it happened.
     *
     * Passing null means "every visible index".
     *
     * @return Mage_Index_Model_Process[] dependency-first, de-duplicated
     */
    public function buildQueue(?array $processIds = null): array
    {
        /** @var Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getSingleton('index/indexer');

        $requested = [];
        if ($processIds === null) {
            foreach ($indexer->getProcessesCollection() as $process) {
                $requested[] = $process;
            }
        } else {
            foreach ($processIds as $processId) {
                $process = $indexer->getProcessById((int) $processId);
                if ($process) {
                    $requested[] = $process;
                }
            }
        }

        $queue = [];
        foreach ($requested as $process) {
            $this->addToQueue($process, $queue, $indexer);
        }

        return array_values($queue);
    }

    /**
     * Reindex every process in the queue, writing per-index state to the progress record.
     *
     * A failing index is recorded and the batch carries on: with a dozen indexes queued, aborting
     * the lot because one threw is the worse outcome.
     */
    public function run(array $processes, Mage_Index_Model_Progress $progress): void
    {
        /** @var Mage_Core_Model_Lock $lock */
        $lock = Mage::getSingleton('core/lock');

        foreach ($processes as $process) {
            $code = $process->getIndexerCode();

            if ($process->isLocked()) {
                // reindexEverything() resolves dependencies through the same indexer singleton, so
                // without this flag a later step in this queue would rebuild the index we just
                // declined to touch, outside any step and unreported
                $process->setData('runed_reindexall', true);
                $progress->skipStep($code, Mage::helper('index')->__('Already running, skipped.'));
                continue;
            }

            $lockName = self::lockName((string) $progress->getToken(), $code);
            $lock->acquire($lockName);

            $progress->startStep($code);
            $startTime = microtime(true);

            try {
                $process->reindexEverything();
                $progress->finishStep($code, microtime(true) - $startTime);
            } catch (Mage_Core_Exception $e) {
                $progress->failStep($code, $e->getMessage(), microtime(true) - $startTime);
            } catch (Throwable $e) {
                // Only Mage_Core_Exception messages are written to be read by a human; anything
                // else can carry SQL, table names or paths, so it stays in exception.log
                Mage::logException($e);
                $progress->failStep(
                    $code,
                    Mage::helper('index')->__('There was a problem with reindexing process.'),
                    microtime(true) - $startTime,
                );
            } finally {
                $lock->release($lockName);
            }
        }

        $progress->finish();
    }

    /**
     * Convert a queue entry into the shape Mage_Index_Model_Progress::init() expects.
     */
    public function getSteps(array $processes): array
    {
        $steps = [];
        foreach ($processes as $process) {
            $steps[] = [
                'code' => $process->getIndexerCode(),
                'name' => $process->getIndexer()->getName(),
            ];
        }
        return $steps;
    }

    /**
     * @param array<int, Mage_Index_Model_Process> $queue keyed by process id
     */
    protected function addToQueue(Mage_Index_Model_Process $process, array &$queue, Mage_Index_Model_Indexer $indexer): void
    {
        $id = (int) $process->getId();
        if (isset($queue[$id]) || !$process->getIndexer()->isVisible()) {
            return;
        }

        // Reserve the slot before recursing, so a dependency cycle cannot loop forever
        $queue[$id] = $process;

        foreach ($process->getDepends() as $code) {
            $dependency = $indexer->getProcessByCode($code);
            if ($dependency) {
                $this->addToQueue($dependency, $queue, $indexer);
            }
        }

        // Re-append so dependencies, added above, come first
        unset($queue[$id]);
        $queue[$id] = $process;
    }
}
