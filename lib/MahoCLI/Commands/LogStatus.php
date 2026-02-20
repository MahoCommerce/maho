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
    name: 'log:status',
    description: 'Show status for log tables in the database',
)]
class LogStatus extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $table = new Table($output);
        $table->setHeaders([
            'Table name',
            'Row count',
            'Data size',
            'Index size',
        ]);

        $logResource = Mage::getModel('log/log')->getResource();
        $db = $logResource->getReadConnection();

        $logTables = [
            $logResource->getTable('log/customer'),
            $logResource->getTable('log/visitor'),
            $logResource->getTable('log/visitor_info'),
            $logResource->getTable('log/url_table'),
            $logResource->getTable('log/url_info_table'),
            $logResource->getTable('log/quote_table'),
            $logResource->getTable('reports/viewed_product_index'),
            $logResource->getTable('reports/compared_product_index'),
            $logResource->getTable('reports/event'),
            $logResource->getTable('catalog/compare_item'),
        ];

        $totalRows = $totalDataLength = $totalIndexLength = 0;
        foreach ($logTables as $logTable) {
            $tableStatus = $db->fetchRow('SHOW TABLE STATUS LIKE ?', $logTable);
            if (!$tableStatus) {
                continue;
            }

            $totalRows += (int) $tableStatus['Rows'];
            $totalDataLength += (int) $tableStatus['Data_length'];
            $totalIndexLength += (int) $tableStatus['Index_length'];

            $table->addRow([
                $tableStatus['Name'],
                number_format((int) $tableStatus['Rows']),
                $this->humanReadableSize((int) $tableStatus['Data_length']),
                $this->humanReadableSize((int) $tableStatus['Index_length']),
            ]);
        }

        $table->addRow([
            'TOTAL',
            number_format($totalRows),
            $this->humanReadableSize($totalDataLength),
            $this->humanReadableSize($totalIndexLength),
        ]);

        $table->render();

        return Command::SUCCESS;
    }
}
