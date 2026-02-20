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
    name: 'cron:list',
    description: 'List cron jobs configured in the XML files',
)]
class CronList extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $table = new Table($output);
        $table->setHeaders(['event', 'model::method', 'schedule']);

        $jobs = [];
        $cronNode = Mage::getConfig()->getNode('crontab/jobs');
        $defaultCronNode = Mage::getConfig()->getNode('default/crontab/jobs');

        if ($cronNode) {
            $jobs = array_merge($jobs, $cronNode->asArray());
        }
        if ($defaultCronNode) {
            $jobs = array_merge($jobs, $defaultCronNode->asArray());
        }
        ksort($jobs, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($jobs as $jobName => $jobConfiguration) {
            if (@$jobConfiguration['schedule']['config_path']) {
                $jobConfiguration['schedule']['cron_expr'] = Mage::getStoreConfig($jobConfiguration['schedule']['config_path']);
            }

            $table->addRow([
                $jobName,
                $jobConfiguration['run']['model'],
                $jobConfiguration['schedule']['cron_expr'] ?? '',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
