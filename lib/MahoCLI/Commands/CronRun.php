<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Cron_Model_Observer;
use Mage_Cron_Model_Schedule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:run',
    description: 'Run a group of cron processes (default/always) or a single job_code (eg: newsletter_send_all)',
)]
class CronRun extends BaseMahoCommand
{
    protected ?\SplFileObject $lockHandle = null;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, '"default" or "always"');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $modeOrJobCode = $input->getArgument('mode');

        // Does the user want to run the default system cron jobs?
        $mode = $modeOrJobCode;
        $availableModes = ['default', 'always'];
        if (in_array($mode, $availableModes)) {
            if (!$this->acquireLock("cron.{$mode}")) {
                $output->writeln("<error>{$mode} is already running</error>");
                return Command::INVALID;
            }

            Mage::getConfig()->init()->loadEventObservers('crontab');
            Mage::app()->addEventArea('crontab');
            Mage::dispatchEvent($mode);
            return Command::SUCCESS;
        }

        // If the job is in the cron_schedule table, we execute it and "burn" that record
        $jobCode = $modeOrJobCode;

        if (!Mage::helper('cron')->isJobEnabled($jobCode)) {
            $output->writeln("<comment>{$jobCode} is disabled in admin. Running anyway via CLI.</comment>");
        }

        $jobConfig = $this->getJobConfig($jobCode);
        if (!$jobConfig) {
            $output->writeln("<error>Unknown mode/job: {$modeOrJobCode}</error>");
            return Command::FAILURE;
        }
        $runConfig = $jobConfig['run'];
        if (!$runConfig['model']) {
            $output->writeln("<error>Invalid model definition for {$jobCode}</error>");
            return Command::FAILURE;
        }
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, (string) $runConfig['model'], $run)) {
            $output->writeln('<error>Invalid model/method definition, expecting "model/class::method"</error>');
            return Command::FAILURE;
        }
        if (!($model = Mage::getModel($run[1])) || !method_exists($model, $run[2])) {
            $output->writeln("<error>Invalid callback: {$run[1]}::{$run[2]} does not exist</error>");
            return Command::FAILURE;
        }

        /** @var Mage_Cron_Model_Schedule $schedule */
        $schedule = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->addFieldToFilter('job_code', $jobCode)
            ->orderByScheduledAt()
            ->getFirstItem();
        $hasPersistentSchedule = (bool) $schedule->getId();

        if ($hasPersistentSchedule) {
            if (!$schedule->tryLockJob()) {
                $output->writeln("<error>{$jobCode} is already running</error>");
                return Command::INVALID;
            }

            $schedule
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
                ->setExecutedAt(\Mage::app()->getLocale()->formatDateForDb('now'))
                ->save();
        } else {
            $schedule = Mage::getModel('cron/schedule');
            $schedule->setJobCode($jobCode);
        }

        try {
            $callback = [$model, $run[2]];
            call_user_func($callback, $schedule);
        } catch (\Exception $e) {
            if ($hasPersistentSchedule) {
                $schedule
                    ->setFinishedAt(\Mage::app()->getLocale()->formatDateForDb('now'))
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                    ->setMessages($e->__toString())
                    ->save();
            }

            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($hasPersistentSchedule) {
            $schedule
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
                ->setFinishedAt(\Mage::app()->getLocale()->formatDateForDb('now'))
                ->save();
        }

        $output->writeln("<info>{$jobCode} executed successfully</info>");
        return Command::SUCCESS;
    }

    protected function getJobConfig(string $jobCode): array|false
    {
        $jobConfig = Mage::getConfig()->getNode("crontab/jobs/{$jobCode}")->asArray();
        if ($jobConfig) {
            return $jobConfig;
        }

        $jobConfig = Mage::getConfig()->getNode("default/crontab/jobs/{$jobCode}")->asArray();
        if ($jobConfig) {
            return $jobConfig;
        }

        return false;
    }

    /**
     * Acquire an exclusive, non-blocking lock for the given name.
     * The lock is tied to this process's file handle: the kernel releases it
     * automatically on exit or crash, so stale locks are impossible.
     *
     * @throws \RuntimeException when the lock file cannot be created
     */
    protected function acquireLock(string $name): bool
    {
        $lockDir = Mage::getConfig()->getVarDir('locks');
        if ($lockDir === false) {
            throw new \RuntimeException('Unable to create lock directory in var/locks');
        }

        $file = $lockDir . DS . $name . '.lock';
        try {
            $handle = new \SplFileObject($file, 'c');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Unable to create lock file {$file}", 0, $e);
        }

        if (!$handle->flock(LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->lockHandle = $handle;
        return true;
    }
}
