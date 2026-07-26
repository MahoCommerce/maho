<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:unlock',
    description: 'Reset stale "running" cron schedules left behind by a crashed or killed worker',
)]
class CronUnlock extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('job_code', InputArgument::OPTIONAL, 'Only unlock schedules for this job_code');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Unlock every running schedule regardless of how long it has been running');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $jobCode = $input->getArgument('job_code');
        $unlockAll = (bool) $input->getOption('all');

        $threshold = null;
        if (!$unlockAll) {
            $maxRunningTime = (int) Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME);
            if ($maxRunningTime <= 0) {
                $maxRunningTime = 60;
            }
            $threshold = time() - $maxRunningTime * 60;
        }

        $collection = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_RUNNING);
        if (is_string($jobCode) && $jobCode !== '') {
            $collection->addFieldToFilter('job_code', $jobCode);
        }
        $collection->load();

        $count = 0;
        foreach ($collection->getIterator() as $schedule) {
            if ($threshold !== null) {
                $referenceTime = $schedule->getExecutedAt() ?: $schedule->getScheduledAt() ?: $schedule->getCreatedAt();
                if (!$referenceTime || strtotime($referenceTime) >= $threshold) {
                    continue;
                }
            }

            $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                ->setMessages('Manually reset via cron:unlock: job never completed')
                ->setFinishedAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->save();

            $output->writeln("<info>Unlocked schedule #{$schedule->getId()} ({$schedule->getJobCode()})</info>");
            $count++;
        }

        if ($count === 0) {
            $output->writeln('<comment>No stale running schedules found</comment>');
        } else {
            $output->writeln("<info>{$count} schedule(s) unlocked</info>");
        }

        return Command::SUCCESS;
    }
}
