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
    name: 'maintenance:status',
    description: 'Show maintenance mode status',
)]
class MaintenanceStatus extends Command
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenanceFile = BP . '/maintenance.flag';
        $maintenanceIpFile = BP . '/maintenance.ip';

        if (!file_exists($maintenanceFile)) {
            $output->writeln('<info>Maintenance mode is disabled</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<comment>Maintenance mode is enabled</comment>');

        if (file_exists($maintenanceIpFile) && is_readable($maintenanceIpFile)) {
            $contents = file_get_contents($maintenanceIpFile);
            if ($contents) {
                $ips = preg_split('/[\s,]+/', $contents, -1, PREG_SPLIT_NO_EMPTY);
                if ($ips) {
                    $output->writeln('<info>Allowed IPs:</info>');
                    foreach ($ips as $ip) {
                        $output->writeln("  - $ip");
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
