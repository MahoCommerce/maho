<?php

/**
 * Run a cron job now and reset its stale running schedules, for the admin and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

use Mage_Cron_Model_Schedule as Schedule;

class Mage_Cron_Service_Job
{
    /**
     * Run the cron job $jobCode now, outside its schedule. A schedule records the run: RUNNING, then SUCCESS,
     * or ERROR with the message of the exception or when the job calls setIsError() on the schedule.
     * With $usePending, the run takes the first pending schedule of the job, so that the scheduler does not
     * run it a second time. Without a pending schedule, and without $usePending, the run gets a new schedule.
     * $started gets the schedule when it is RUNNING, before the job starts.
     * The job holds its lock while it runs, so that a second run of the same job cannot start.
     *
     * @param (callable(Schedule): void)|null $started
     * @throws Mage_Core_Exception_NoSuchEntity when no enabled module declares $jobCode
     * @throws Mage_Core_Exception when the job declares a callback that does not exist
     * @throws Mage_Core_Exception_Conflict when the job runs already
     */
    public function run(string $jobCode, ?callable $started = null, bool $usePending = false): Schedule
    {
        $callback = $this->getCallback($jobCode);
        if (!$this->acquireLock($jobCode)) {
            throw $this->alreadyRunning($jobCode);
        }

        try {
            $now = Mage::app()->getLocale()->formatDateForDb('now');
            $schedule = $usePending ? $this->getPendingSchedule($jobCode) : null;
            if ($schedule === null) {
                /** @var Schedule $schedule */
                $schedule = Mage::getModel('cron/schedule');
                $schedule->setJobCode($jobCode)
                    ->setCreatedAt($now)
                    ->setScheduledAt($now);
            } elseif (!$schedule->tryLockJob()) {
                throw $this->alreadyRunning($jobCode);
            }
            $schedule->setStatus(Schedule::STATUS_RUNNING)
                ->setExecutedAt($now)
                ->save();
            if ($started !== null) {
                $started($schedule);
            }

            $this->execute($schedule, $callback);
            $schedule->save();
            return $schedule;
        } finally {
            $this->releaseLock($jobCode);
        }
    }

    protected function getPendingSchedule(string $jobCode): ?Schedule
    {
        /** @var Schedule $schedule */
        $schedule = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', Schedule::STATUS_PENDING)
            ->addFieldToFilter('job_code', $jobCode)
            ->orderByScheduledAt()
            ->setPageSize(1)
            ->getFirstItem();
        return $schedule->getId() ? $schedule : null;
    }

    protected function alreadyRunning(string $jobCode): Mage_Core_Exception_Conflict
    {
        return new Mage_Core_Exception_Conflict(Mage::helper('cron')->__('The cron job %s is already running.', $jobCode));
    }

    /**
     * Call $callback for $schedule, for a run now and for a run of the scheduler. Set the schedule to SUCCESS, or to
     * ERROR when the job throws or calls setIsError() on the schedule. The caller saves the schedule.
     */
    public function execute(Schedule $schedule, callable $callback): void
    {
        \Maho\Profiler::start('cron.job.execute', ['cron.job_code' => $schedule->getJobCode()]);
        try {
            $callback($schedule);
            $schedule->setStatus($schedule->getIsError() ? Schedule::STATUS_ERROR : Schedule::STATUS_SUCCESS);
        } catch (Throwable $e) {
            $this->recordError($schedule, $e);
        } finally {
            \Maho\Profiler::stop('cron.job.execute');
        }
        $schedule->setFinishedAt(Mage::app()->getLocale()->formatDateForDb('now'));
    }

    /**
     * Set $schedule to ERROR with the message of $e. The exception log gets the stack trace. The caller saves the schedule.
     */
    public function recordError(Schedule $schedule, Throwable $e): void
    {
        Mage::logException($e);
        $schedule->setStatus(Schedule::STATUS_ERROR)->setMessages($e->getMessage());
    }

    /**
     * Set each running schedule whose run is stale to ERROR, and return these schedules. A run is stale when it
     * started more than system/cron/max_running_time minutes ago, or at any time when $all is true. A schedule
     * of a job that a process runs now, and so holds the lock of the job, is not stale. $message goes into the
     * messages of each schedule, in place of the message of cron:unlock.
     *
     * @return list<Schedule>
     */
    public function unlock(?string $jobCode = null, bool $all = false, ?string $message = null): array
    {
        $message ??= Mage::helper('cron')->__('Manually reset via cron:unlock: job never completed.');
        $threshold = null;
        if (!$all) {
            $maxRunningTime = (int) Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME);
            $threshold = time() - ($maxRunningTime > 0 ? $maxRunningTime : 60) * 60;
        }

        /** @var Mage_Cron_Model_Resource_Schedule_Collection $collection */
        $collection = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', Schedule::STATUS_RUNNING);
        if ($jobCode !== null && $jobCode !== '') {
            $collection->addFieldToFilter('job_code', $jobCode);
        }

        $unlocked = [];
        /** @var Schedule $schedule */
        foreach ($collection->getIterator() as $schedule) {
            if ($threshold !== null) {
                $startedAt = (string) ($schedule->getExecutedAt() ?: $schedule->getScheduledAt() ?: $schedule->getCreatedAt());
                if ($startedAt === '' || strtotime($startedAt) >= $threshold) {
                    continue;
                }
            }
            if ($this->isRunning((string) $schedule->getJobCode())) {
                continue;
            }
            $schedule->setStatus(Schedule::STATUS_ERROR)
                ->setMessages($message)
                ->setFinishedAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->save();
            $unlocked[] = $schedule;
        }
        return $unlocked;
    }

    /**
     * Take the lock of $jobCode, and return false when another run holds it. The lock ends with the process.
     */
    public function acquireLock(string $jobCode): bool
    {
        return Mage::getSingleton('core/lock')->acquire($this->getLockName($jobCode));
    }

    public function releaseLock(string $jobCode): void
    {
        Mage::getSingleton('core/lock')->release($this->getLockName($jobCode));
    }

    /**
     * Whether a process runs $jobCode now.
     */
    private function isRunning(string $jobCode): bool
    {
        return Mage::getSingleton('core/lock')->isHeld($this->getLockName($jobCode));
    }

    protected function getLockName(string $jobCode): string
    {
        return "cron.job.{$jobCode}";
    }

    /**
     * Return the callback that the "model/class::method" definition of $jobCode names.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when no enabled module declares $jobCode
     * @throws Mage_Core_Exception when the callback does not exist
     */
    protected function getCallback(string $jobCode): callable
    {
        $helper = Mage::helper('cron');
        $job = $helper->getConfiguredJobs()[$jobCode] ?? null;
        if ($job === null) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('Unknown cron job: %s', $jobCode));
        }
        return $this->resolveCallback((string) $job['model_method']);
    }

    /**
     * Return the callback that the "model/class::method" definition $modelMethod names.
     *
     * @throws Mage_Core_Exception when the definition is not valid or the callback does not exist
     */
    public function resolveCallback(string $modelMethod): callable
    {
        $helper = Mage::helper('cron');
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, $modelMethod, $run)) {
            throw new Mage_Core_Exception($helper->__('Invalid model/method definition, expecting "model/class::method".'));
        }
        $model = Mage::getModel($run[1]);
        if (!$model || !method_exists($model, $run[2])) {
            throw new Mage_Core_Exception($helper->__('Invalid callback: %s::%s does not exist', $run[1], $run[2]));
        }
        return [$model, $run[2]];
    }
}
