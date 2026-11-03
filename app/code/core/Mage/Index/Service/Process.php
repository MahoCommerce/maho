<?php

/**
 * Find the index processes of System > Index Management, change their mode and reindex them, for the admin, the API and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Maho\Job\StepState;

class Mage_Index_Service_Process
{
    /**
     * @return list<Mage_Index_Model_Process> the processes that System > Index Management lists
     */
    public function getList(): array
    {
        $processes = [];
        /** @var Mage_Index_Model_Process $process */
        foreach (Mage::getResourceModel('index/process_collection') as $process) {
            if ($process->getIndexer()->isVisible()) {
                $processes[] = $process;
            }
        }
        return $processes;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when no visible process has the ID $id
     */
    public function getById(int $id): Mage_Index_Model_Process
    {
        /** @var Mage_Index_Model_Process $process */
        $process = Mage::getModel('index/process')->load($id);
        if (!$process->getId() || !$process->getIndexer()->isVisible()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('index')->__('Index process %s not found', $id));
        }
        return $process;
    }

    /**
     * Return the process of the indexer $code. The process comes from the indexer singleton, so a reindex
     * of a queue of such processes runs each dependency once.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when no visible process has the code $code
     */
    public function getByCode(string $code): Mage_Index_Model_Process
    {
        $process = Mage::getSingleton('index/indexer')->getProcessByCode($code);
        if (!$process || !$process->getIndexer()->isVisible()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('index')->__('Index "%s" not found', $code));
        }
        return $process;
    }

    /**
     * @throws Mage_Core_Exception_Input when $mode is not real_time or manual
     */
    public function setMode(Mage_Index_Model_Process $process, string $mode): void
    {
        if (!array_key_exists($mode, $process->getModesOptions())) {
            throw new Mage_Core_Exception_Input()
                ->addError('mode', Mage::helper('index')->__('mode must be real_time or manual'));
        }
        $process->setMode($mode)->save();
    }

    /**
     * Return the processes that a reindex of $processIds runs, dependencies first and each one once.
     * Null means every visible process.
     *
     * @param array<int|string>|null $processIds
     * @return list<Mage_Index_Model_Process>
     */
    public function getQueue(?array $processIds = null): array
    {
        $this->resetIndexer();
        return Mage::getModel('index/runner')->buildQueue($processIds);
    }

    /**
     * Rebuild the index of $process and of the indexes that it depends on.
     *
     * @throws Mage_Core_Exception_Conflict when the index is working now, or the index refuses the reindex
     */
    public function reindex(Mage_Index_Model_Process $process): void
    {
        $this->resetIndexer();
        if ($process->isLocked()) {
            throw new Mage_Core_Exception_Conflict($this->lockedMessage($process));
        }
        try {
            $process->reindexEverything();
        } catch (Mage_Core_Exception $e) {
            // The index cannot be rebuilt from its current data, which is the state of the record
            throw $e instanceof Mage_Core_Exception_Conflict ? $e : new Mage_Core_Exception_Conflict($e->getMessage(), 0, $e);
        }
    }

    /**
     * Rebuild each process of $queue in order. An index that is working now is skipped, and an index that fails
     * does not stop the others. $onStart gets each process before its step, and $onEnd gets its result.
     *
     * @param list<Mage_Index_Model_Process> $queue a queue of getQueue(), or processes of getByCode()
     * @param (callable(Mage_Index_Model_Process): void)|null $onStart
     * @param (callable(Mage_Index_Model_Process, array{state: StepState, duration: float, message: ?string, error: ?Throwable}): void)|null $onEnd
     * @return array<string, array{state: StepState, duration: float, message: ?string, error: ?Throwable}> by indexer code
     */
    public function reindexQueue(array $queue, ?callable $onStart = null, ?callable $onEnd = null): array
    {
        $results = [];
        foreach ($queue as $process) {
            if ($onStart !== null) {
                $onStart($process);
            }
            $result = $this->reindexStep($process);
            $results[(string) $process->getIndexerCode()] = $result;
            if ($onEnd !== null) {
                $onEnd($process, $result);
            }
        }
        return $results;
    }

    /**
     * Rebuild every process of $queue as reindexQueue() does, and throw after the last one when a step did not succeed.
     *
     * @param list<Mage_Index_Model_Process> $queue
     * @param (callable(Mage_Index_Model_Process): void)|null $onStart
     * @param (callable(Mage_Index_Model_Process, array{state: StepState, duration: float, message: ?string, error: ?Throwable}): void)|null $onEnd
     * @throws Mage_Core_Exception_Conflict when an index was working or refused the reindex
     * @throws RuntimeException when an index failed for another reason
     */
    public function reindexAll(array $queue, ?callable $onStart = null, ?callable $onEnd = null): void
    {
        $problems = [];
        $fault = null;
        $results = $this->reindexQueue($queue, $onStart, $onEnd);
        foreach ($queue as $process) {
            $result = $results[(string) $process->getIndexerCode()];
            $problems[] = match ($result['state']) {
                StepState::Success => null,
                StepState::Skipped => $this->lockedMessage($process),
                default => (string) $result['message'],
            };
            if ($result['error'] !== null && !$result['error'] instanceof Mage_Core_Exception) {
                $fault ??= $result['error'];
            }
        }
        $problems = array_filter($problems);
        if ($fault !== null) {
            throw new RuntimeException('An index failed during the reindex.', 0, $fault);
        }
        if ($problems !== []) {
            throw new Mage_Core_Exception_Conflict(implode("\n", $problems));
        }
    }

    /**
     * @return array{state: StepState, duration: float, message: ?string, error: ?Throwable}
     */
    private function reindexStep(Mage_Index_Model_Process $process): array
    {
        if ($process->isLocked()) {
            // reindexEverything() finds a dependency through the indexer singleton, so without this flag a later
            // step of the queue would rebuild the index that this step leaves alone
            $process->setData('runed_reindexall', true);
            return ['state' => StepState::Skipped, 'duration' => 0.0, 'message' => Mage::helper('index')->__('Already running, skipped.'), 'error' => null];
        }

        $startTime = microtime(true);
        try {
            $process->reindexEverything();
            return ['state' => StepState::Success, 'duration' => microtime(true) - $startTime, 'message' => null, 'error' => null];
        } catch (Mage_Core_Exception $e) {
            return ['state' => StepState::Error, 'duration' => microtime(true) - $startTime, 'message' => $e->getMessage(), 'error' => $e];
        } catch (Throwable $e) {
            // Only the message of a Mage_Core_Exception is written for a person. Another message can hold SQL or a path.
            Mage::logException($e);
            return [
                'state' => StepState::Error,
                'duration' => microtime(true) - $startTime,
                'message' => Mage::helper('index')->__('There was a problem with reindexing process.'),
                'error' => $e,
            ];
        }
    }

    private function lockedMessage(Mage_Index_Model_Process $process): string
    {
        return Mage::helper('index')->__('Index "%s" is working now. Try again later.', $process->getIndexer()->getName());
    }

    /**
     * A long worker keeps the indexer singleton, and its processes keep the done flag of an earlier reindex.
     */
    private function resetIndexer(): void
    {
        Mage::unregister('_singleton/index/indexer');
    }
}
