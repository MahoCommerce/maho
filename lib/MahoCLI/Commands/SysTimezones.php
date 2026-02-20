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
    name: 'sys:timezones',
    description: 'Get all available timezones',
)]
class SysTimezones extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $timezones = Mage::app()->getLocale()->getOptionTimezones();

        $table = new Table($output);
        $table->setHeaders(['code', 'description']);
        foreach ($timezones as $timezone) {
            $table->addRow([
                $timezone['value'],
                $timezone['label'],
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
