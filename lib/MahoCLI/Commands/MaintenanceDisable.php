<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'maintenance:disable',
    description: 'Disable maintenance mode',
)]
class MaintenanceDisable extends Command
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenanceFile = BP . '/maintenance.flag';
        $maintenanceIpFile = BP . '/maintenance.ip';

        if (!file_exists($maintenanceFile)) {
            $output->writeln('<comment>Maintenance mode is not enabled</comment>');
            return Command::SUCCESS;
        }

        if (!unlink($maintenanceFile)) {
            $output->writeln('<error>Failed to remove maintenance.flag file</error>');
            return Command::FAILURE;
        }

        if (file_exists($maintenanceIpFile)) {
            unlink($maintenanceIpFile);
        }

        $output->writeln('<info>Maintenance mode disabled</info>');

        return Command::SUCCESS;
    }
}
