<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Model_Observer
{
    public const CACHE_KEY_LAST_SCHEDULE_GENERATE_AT   = 'cron_last_schedule_generate_at';
    public const CACHE_KEY_LAST_HISTORY_CLEANUP_AT     = 'cron_last_history_cleanup_at';
    public const CACHE_KEY_LAST_CRON_STATUS_CHECK      = 'cron_last_status_check';
    public const CRON_STATUS_CHECK_INTERVAL            = 3600; // 1 hour
    public const CRON_NOT_RUNNING_THRESHOLD            = 3600; // 1 hour

    public const XML_PATH_SCHEDULE_GENERATE_EVERY  = 'system/cron/schedule_generate_every';
    public const XML_PATH_SCHEDULE_AHEAD_FOR       = 'system/cron/schedule_ahead_for';
    public const XML_PATH_SCHEDULE_LIFETIME        = 'system/cron/schedule_lifetime';
    public const XML_PATH_HISTORY_CLEANUP_EVERY    = 'system/cron/history_cleanup_every';
    public const XML_PATH_HISTORY_SUCCESS          = 'system/cron/history_success_lifetime';
    public const XML_PATH_HISTORY_FAILURE          = 'system/cron/history_failure_lifetime';

    public const REGEX_RUN_MODEL = '#^([a-z0-9_]+/[a-z0-9_]+)::([a-z0-9_]+)$#i';

    protected $_pendingSchedules;

    /**
     * Check if cron is running and warn admin users if not
     */
    #[Maho\Config\Observer('controller_action_predispatch', area: 'adminhtml', id: 'cron_status_check')]
    public function checkCronStatus(\Maho\Event\Observer $observer): void
    {
        if (!Mage::getSingleton('admin/session')->isLoggedIn()) {
            return;
        }

        $now = Mage::getSingleton('core/date')->gmtTimestamp();
        $lastCheck = Mage::app()->loadCache(self::CACHE_KEY_LAST_CRON_STATUS_CHECK);
        if ($lastCheck && $lastCheck > $now - self::CRON_STATUS_CHECK_INTERVAL) {
            return;
        }

        Mage::app()->saveCache($now, self::CACHE_KEY_LAST_CRON_STATUS_CHECK, ['crontab'], null);

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('cron/schedule');

        $threshold = Mage::getSingleton('core/date')->gmtDate(
            'Y-m-d H:i:s',
            $now - self::CRON_NOT_RUNNING_THRESHOLD,
        );

        $hasRecentExecution = $adapter->fetchOne(
            $adapter->select()
                ->from($table, [new \Maho\Db\Expr('1')])
                ->where('executed_at >= ?', $threshold)
                ->limit(1),
        );

        if (!$hasRecentExecution) {
            Mage::getSingleton('adminhtml/session')->addWarning(
                Mage::helper('cron')->__('Cron does not appear to be running. No jobs have been executed in the last hour. Please verify that cron is configured on your server.'),
            );
        }
    }

    /**
     * Process cron queue
     * Generate tasks schedule
     * Cleanup tasks schedule
     *
     * @param \Maho\Event\Observer $observer
     */
    #[Maho\Config\Observer('default', area: 'crontab', id: 'cron_observer')]
    public function dispatch($observer)
    {
        $schedules = $this->getPendingSchedules();
        $jobsRoot = Mage::getConfig()->getNode('crontab/jobs');
        $defaultJobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
        $compiledJobs = Maho::getCompiledAttributes()['crontab'] ?? [];

        /** @var Mage_Cron_Model_Schedule $schedule */
        foreach ($schedules->getIterator() as $schedule) {
            if (!Mage::helper('cron')->isJobEnabled($schedule->getJobCode())) {
                continue;
            }

            $jobCode = $schedule->getJobCode();

            try {
                if (isset($compiledJobs[$jobCode])) {
                    $this->_processCompiledJob($schedule, $compiledJobs[$jobCode]);
                    continue;
                }

                $jobConfig = $jobsRoot->{$jobCode};
                if (!$jobConfig || !$jobConfig->run) {
                    $jobConfig = $defaultJobsRoot->{$jobCode};
                    if (!$jobConfig || !$jobConfig->run) {
                        continue;
                    }
                }
                $this->_processJob($schedule, $jobConfig);
            } catch (Exception $e) {
                $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                    ->setMessages($e->__toString())
                    ->save();
            }
        }

        $this->generate();
        $this->cleanup();
    }

    /**
     * Process cron queue for tasks marked as always
     *
     * @param \Maho\Event\Observer $observer
     */
    #[Maho\Config\Observer('always', area: 'crontab', id: 'cron_observer')]
    public function dispatchAlways($observer)
    {
        $jobsRoot = Mage::getConfig()->getNode('crontab/jobs');
        if ($jobsRoot instanceof \Maho\Simplexml\Element) {
            foreach ($jobsRoot->children() as $jobCode => $jobConfig) {
                $this->_processAlwaysTask($jobCode, $jobConfig);
            }
        }

        $defaultJobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
        if ($defaultJobsRoot instanceof \Maho\Simplexml\Element) {
            foreach ($defaultJobsRoot->children() as $jobCode => $jobConfig) {
                $this->_processAlwaysTask($jobCode, $jobConfig);
            }
        }

        $cronHelper = Mage::helper('cron');
        foreach (Maho::getCompiledAttributes()['crontab'] ?? [] as $jobCode => $jobDef) {
            if ($cronHelper->resolveCompiledCronExpr($jobDef) !== 'always') {
                continue;
            }
            if (!$cronHelper->isJobEnabled($jobCode)) {
                continue;
            }
            $schedule = $this->_getAlwaysJobSchedule($jobCode);
            if ($schedule !== false) {
                try {
                    $this->_processCompiledJob($schedule, $jobDef, true);
                } catch (Exception $e) {
                    $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                        ->setMessages($e->__toString())
                        ->save();
                }
            }
        }
    }

    /**
     * @return Mage_Cron_Model_Resource_Schedule_Collection
     */
    public function getPendingSchedules()
    {
        if (!$this->_pendingSchedules) {
            $this->_pendingSchedules = Mage::getModel('cron/schedule')->getCollection()
                ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
                ->orderByScheduledAt()
                ->load();
        }
        return $this->_pendingSchedules;
    }

    /**
     * Generate cron schedule
     *
     * @return $this
     */
    public function generate()
    {
        /**
         * check if schedule generation is needed
         */
        $lastRun = Mage::app()->loadCache(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);
        if ($lastRun > Mage::getSingleton('core/date')->gmtTimestamp() - Mage::getStoreConfig(self::XML_PATH_SCHEDULE_GENERATE_EVERY) * 60) {
            return $this;
        }

        $schedules = $this->getPendingSchedules();
        $exists = [];
        foreach ($schedules->getIterator() as $schedule) {
            $exists[$schedule->getJobCode() . '/' . $schedule->getScheduledAt()] = 1;
        }

        /**
         * generate global crontab jobs
         */
        $config = Mage::getConfig()->getNode('crontab/jobs');
        if ($config instanceof Mage_Core_Model_Config_Element) {
            $this->_generateJobs($config->children(), $exists);
        }

        /**
         * generate configurable crontab jobs
         */
        $config = Mage::getConfig()->getNode('default/crontab/jobs');
        if ($config instanceof Mage_Core_Model_Config_Element) {
            $this->_generateJobs($config->children(), $exists);
        }

        $this->_generateCompiledJobs($exists);

        /**
         * save time schedules generation was ran with no expiration
         */
        Mage::app()->saveCache(Mage::getSingleton('core/date')->gmtTimestamp(), self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT, ['crontab'], null);

        return $this;
    }

    /**
     * Generate jobs for config information
     *
     * @param   SimpleXMLElement $jobs
     * @param   array $exists
     * @return  $this
     */
    protected function _generateJobs($jobs, $exists)
    {
        $scheduleAheadFor = Mage::getStoreConfig(self::XML_PATH_SCHEDULE_AHEAD_FOR) * 60;
        $schedule = Mage::getModel('cron/schedule');

        foreach ($jobs as $jobCode => $jobConfig) {
            if (!Mage::helper('cron')->isJobEnabled($jobCode)) {
                continue;
            }
            $cronExpr = null;
            if ($jobConfig->schedule->config_path) {
                $cronExpr = Mage::getStoreConfig((string) $jobConfig->schedule->config_path);
            }
            if (empty($cronExpr) && $jobConfig->schedule->cron_expr) {
                $cronExpr = (string) $jobConfig->schedule->cron_expr;
            }
            if (!$cronExpr || $cronExpr == 'always') {
                continue;
            }

            $now = Mage::getSingleton('core/date')->gmtTimestamp();
            $timeAhead = $now + $scheduleAheadFor;
            $schedule->setJobCode($jobCode)
                ->setCronExpr($cronExpr)
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING);

            for ($time = $now; $time < $timeAhead; $time += 60) {
                $ts = gmdate('Y-m-d H:i:00', $time);
                if (!empty($exists[$jobCode . '/' . $ts])) {
                    // already scheduled
                    continue;
                }
                if (!$schedule->trySchedule($time)) {
                    // time does not match cron expression
                    continue;
                }
                $schedule->unsScheduleId()->save();
            }
        }
        return $this;
    }

    /**
     * Clean up the history of tasks
     *
     * @return $this
     */
    public function cleanup()
    {
        // check if history cleanup is needed
        $lastCleanup = Mage::app()->loadCache(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);
        if ($lastCleanup > Mage::getSingleton('core/date')->gmtTimestamp() - Mage::getStoreConfig(self::XML_PATH_HISTORY_CLEANUP_EVERY) * 60) {
            return $this;
        }

        $history = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', ['in' => [
                Mage_Cron_Model_Schedule::STATUS_SUCCESS,
                Mage_Cron_Model_Schedule::STATUS_MISSED,
                Mage_Cron_Model_Schedule::STATUS_ERROR,
            ]])
            ->load();

        $historyLifetimes = [
            Mage_Cron_Model_Schedule::STATUS_SUCCESS => Mage::getStoreConfig(self::XML_PATH_HISTORY_SUCCESS) * 60,
            Mage_Cron_Model_Schedule::STATUS_MISSED => Mage::getStoreConfig(self::XML_PATH_HISTORY_FAILURE) * 60,
            Mage_Cron_Model_Schedule::STATUS_ERROR => Mage::getStoreConfig(self::XML_PATH_HISTORY_FAILURE) * 60,
        ];

        $now = Mage::getSingleton('core/date')->gmtTimestamp();
        foreach ($history->getIterator() as $record) {
            $referenceTime = $record->getExecutedAt() ?: $record->getScheduledAt();
            if ($referenceTime && strtotime($referenceTime) < $now - $historyLifetimes[$record->getStatus()]) {
                $record->delete();
            }
        }

        // save time history cleanup was ran with no expiration
        Mage::app()->saveCache(Mage::getSingleton('core/date')->gmtTimestamp(), self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT, ['crontab'], null);

        return $this;
    }

    /**
     * Processing cron task which is marked as always
     *
     * @param string $jobCode
     * @param SimpleXMLElement $jobConfig
     * @return $this|void
     */
    protected function _processAlwaysTask($jobCode, $jobConfig)
    {
        if (!$jobConfig || !$jobConfig->run) {
            return;
        }

        if (!Mage::helper('cron')->isJobEnabled($jobCode)) {
            return;
        }

        $cronExpr = isset($jobConfig->schedule->cron_expr) ? (string) $jobConfig->schedule->cron_expr : '';
        if ($cronExpr != 'always') {
            return;
        }

        $schedule = $this->_getAlwaysJobSchedule($jobCode);
        if ($schedule !== false) {
            $this->_processJob($schedule, $jobConfig, true);
        }

        return $this;
    }

    /**
     * Process cron task
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @param SimpleXMLElement $jobConfig
     * @param bool $isAlways
     */
    protected function _processJob($schedule, $jobConfig, $isAlways = false): self
    {
        $runConfig = $jobConfig->run;
        if (!$runConfig->model) {
            Mage::throwException(Mage::helper('cron')->__('No callbacks found'));
        }
        if (!preg_match(self::REGEX_RUN_MODEL, (string) $runConfig->model, $run)) {
            Mage::throwException(Mage::helper('cron')->__('Invalid model/method definition, expecting "model/class::method".'));
        }
        if (!($model = Mage::getModel($run[1])) || !method_exists($model, $run[2])) {
            Mage::throwException(Mage::helper('cron')->__('Invalid callback: %s::%s does not exist', $run[1], $run[2]));
        }

        return $this->_executeScheduledJob($schedule, [$model, $run[2]], $isAlways);
    }

    /**
     * Process a compiled (attribute-registered) cron job.
     */
    protected function _processCompiledJob(
        Mage_Cron_Model_Schedule $schedule,
        array $jobDef,
        bool $isAlways = false,
    ): self {
        $model = Mage::getModel($jobDef['alias']);
        if (!$model || !method_exists($model, $jobDef['method'])) {
            Mage::throwException(Mage::helper('cron')->__('Invalid callback: %s::%s does not exist', $jobDef['alias'], $jobDef['method']));
        }

        return $this->_executeScheduledJob($schedule, [$model, $jobDef['method']], $isAlways);
    }

    /**
     * Execute a scheduled job with lifetime checks, locking, and status management.
     */
    protected function _executeScheduledJob(Mage_Cron_Model_Schedule $schedule, callable $callback, bool $isAlways = false): self
    {
        if (!$isAlways) {
            $scheduleLifetime = Mage::getStoreConfig(self::XML_PATH_SCHEDULE_LIFETIME) * 60;
            $now = Mage::getSingleton('core/date')->gmtTimestamp();
            $time = strtotime($schedule->getScheduledAt());
            if ($time > $now) {
                return $this;
            }
        }

        $errorStatus = Mage_Cron_Model_Schedule::STATUS_ERROR;
        try {
            if (!$isAlways) {
                if ($time < $now - $scheduleLifetime) {
                    $errorStatus = Mage_Cron_Model_Schedule::STATUS_MISSED;
                    Mage::throwException(Mage::helper('cron')->__('Too late for the schedule.'));
                }
            }

            if (!$isAlways) {
                if (!$schedule->tryLockJob()) {
                    // another cron started this job intermittently, so skip it
                    return $this;
                }
                /**
                though running status is set in tryLockJob we must set it here because the object
                was loaded with a pending status and will set it back to pending if we don't set it here
                 */
                $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING);
            }

            $schedule
                ->setExecutedAt(Mage::getSingleton('core/date')->gmtDate())
                ->save();

            call_user_func_array($callback, [$schedule]);

            $schedule
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
                ->setFinishedAt(Mage::getSingleton('core/date')->gmtDate());
        } catch (Exception $e) {
            $schedule->setStatus($errorStatus)
                ->setMessages($e->__toString());
        }

        if ($schedule->getIsError()) {
            $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR);
        }

        $schedule->save();

        return $this;
    }

    protected function _generateCompiledJobs(array $exists): void
    {
        $scheduleAheadFor = Mage::getStoreConfig(self::XML_PATH_SCHEDULE_AHEAD_FOR) * 60;
        $schedule = Mage::getModel('cron/schedule');
        $cronHelper = Mage::helper('cron');
        $now = Mage::getSingleton('core/date')->gmtTimestamp();
        $timeAhead = $now + $scheduleAheadFor;

        foreach (Maho::getCompiledAttributes()['crontab'] ?? [] as $jobCode => $jobDef) {
            if (!$cronHelper->isJobEnabled($jobCode)) {
                continue;
            }

            $cronExpr = $cronHelper->resolveCompiledCronExpr($jobDef);
            if (!$cronExpr) {
                Mage::log("Cron job '{$jobCode}' has no schedule expression and no config_path, skipping.", Mage::LOG_WARNING);
                continue;
            }
            if ($cronExpr === 'always') {
                continue;
            }
            $schedule->setJobCode($jobCode)
                ->setCronExpr($cronExpr)
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING);

            for ($time = $now; $time < $timeAhead; $time += 60) {
                $ts = gmdate('Y-m-d H:i:00', $time);
                if (!empty($exists[$jobCode . '/' . $ts])) {
                    continue;
                }
                if (!$schedule->trySchedule($time)) {
                    continue;
                }
                $schedule->unsScheduleId()->save();
            }
        }
    }

    /**
     * Get job for task marked as always
     *
     * @param string $jobCode
     * @return Mage_Cron_Model_Schedule
     */
    protected function _getAlwaysJobSchedule($jobCode)
    {
        /** @var Mage_Cron_Model_Schedule $schedule */
        $schedule = Mage::getModel('cron/schedule')->load($jobCode, 'job_code');
        if ($schedule->getId() === null) {
            $ts = Mage::getSingleton('core/date')->gmtDate('Y-m-d H:i:00');
            $schedule->setJobCode($jobCode)
                ->setCreatedAt($ts)
                ->setScheduledAt($ts);
        }
        $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)->save();
        return $schedule;
    }
}
