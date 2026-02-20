<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:history',
    description: 'List cron jobs executions stored in the database',
)]
class CronHistory extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $table = new Table($output);
        $table->setHeaders(['schedule_id', 'job_code', 'status', 'messages',
            'messages', 'scheduled_at', 'executed_at', 'finished_at']);

        $jobs = Mage::getResourceModel('cron/schedule_collection');
        foreach ($jobs as $job) {
            $table->addRow([
                $job->getId(),
                $job->getJobCode(),
                $job->getStatus(),
                $job->getMessages(),
                $job->getCreatedAt(),
                $job->getScheduledAt(),
                $job->getExecutedAt(),
                $job->getFinishedAt(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
