<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Cron_Model_Schedule;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:run',
    description: 'Run a group of cron processes (default/always) or a single job_code (eg: newsletter_send_all)',
)]
class CronRun extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: '"default" or "always"', name: 'mode')]
        string $modeOrJobCode,
    ): int {
        $this->initMaho();

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

            Mage::app()->addEventArea('crontab');
            Mage::dispatchEvent($mode);
            return Command::SUCCESS;
        }

        $jobCode = $modeOrJobCode;
        if (!Mage::helper('cron')->isJobEnabled($jobCode)) {
            $output->writeln("<comment>{$jobCode} is disabled in admin. Running anyway via CLI.</comment>");
        }

        try {
            /** @var \Mage_Cron_Service_Job $service */
            $service = Mage::getService('cron/job');
            $schedule = $service->run($jobCode, usePending: true);
        } catch (\Mage_Core_Exception_Conflict $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::INVALID;
        } catch (\Mage_Core_Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($schedule->getStatus() === Mage_Cron_Model_Schedule::STATUS_ERROR) {
            $output->writeln("<error>{$schedule->getMessages()}</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>{$jobCode} executed successfully</info>");
        return Command::SUCCESS;
    }
}
