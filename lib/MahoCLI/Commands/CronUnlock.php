<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:unlock',
    description: 'Reset stale "running" cron schedules left behind by a crashed or killed worker',
)]
class CronUnlock extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Only unlock schedules for this job_code', name: 'job_code')]
        ?string $jobCode = null,
        #[Option(description: 'Unlock every running schedule regardless of how long it has been running', name: 'all')]
        bool $unlockAll = false,
    ): int {
        $this->initMaho();

        /** @var \Mage_Cron_Service_Job $service */
        $service = Mage::getService('cron/job');
        $unlocked = $service->unlock(trim((string) $jobCode), $unlockAll);
        foreach ($unlocked as $schedule) {
            $output->writeln("<info>Unlocked schedule #{$schedule->getId()} ({$schedule->getJobCode()})</info>");
        }

        if ($unlocked === []) {
            $output->writeln('<comment>No stale running schedules found</comment>');
        } else {
            $output->writeln('<info>' . count($unlocked) . ' schedule(s) unlocked</info>');
        }
        return Command::SUCCESS;
    }
}
