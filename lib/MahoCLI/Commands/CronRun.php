<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho;
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
            try {
                // Held until this process exits
                $acquired = Mage::getSingleton('core/lock')->acquire("cron.{$mode}");
            } catch (\Throwable $e) {
                // Lock could not be created: abort instead of running unguarded
                $output->writeln("<error>{$e->getMessage()}</error>");
                return Command::FAILURE;
            }
            if (!$acquired) {
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

        $runModel = $this->getJobRunModel($jobCode);
        if ($runModel === null) {
            $output->writeln("<error>Unknown mode/job: {$modeOrJobCode}</error>");
            return Command::FAILURE;
        }
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, $runModel, $run)) {
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

    /**
     * Resolve the "model/class::method" run definition for a job_code.
     *
     * Looks up attribute-registered (compiled) cron jobs first, then falls back to
     * legacy XML-declared jobs under crontab/jobs and default/crontab/jobs.
     */
    protected function getJobRunModel(string $jobCode): ?string
    {
        $compiledJobs = Maho::getCompiledAttributes()['crontab'] ?? [];
        if (isset($compiledJobs[$jobCode])) {
            $jobDef = $compiledJobs[$jobCode];
            if (!empty($jobDef['module']) && !Mage::helper('core')->isModuleEnabled($jobDef['module'])) {
                return null;
            }
            return $jobDef['alias'] . '::' . $jobDef['method'];
        }

        foreach (['crontab/jobs', 'default/crontab/jobs'] as $path) {
            $node = Mage::getConfig()->getNode("{$path}/{$jobCode}");
            if ($node && $node->run && (string) $node->run->model !== '') {
                return (string) $node->run->model;
            }
        }

        return null;
    }

}
