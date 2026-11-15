<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:list',
    description: 'List all indexes',
)]
class IndexList extends BaseMahoCommand
{
    public function __invoke(OutputInterface $output): int
    {
        $this->initMaho();
        $table = new Table($output);
        $table->setHeaders(['indexer_code', 'status', 'started_at', 'ended_at', 'mode']);

        /** @var \Mage_Index_Service_Process $service */
        $service = Mage::getService('index/process');
        foreach ($service->getList() as $process) {
            $table->addRow([$process->getIndexerCode(), $process->getStatus(), $process->getStartedAt(), $process->getEndedAt(), $process->getMode()]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
