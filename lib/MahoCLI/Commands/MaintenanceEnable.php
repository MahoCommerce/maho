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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'maintenance:enable',
    description: 'Enable maintenance mode',
)]
class MaintenanceEnable extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'ip',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma-separated list of IPs allowed to bypass maintenance mode',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenanceFile = BP . '/maintenance.flag';
        $maintenanceIpFile = BP . '/maintenance.ip';

        if (file_put_contents($maintenanceFile, '') === false) {
            $output->writeln('<error>Failed to create maintenance.flag file</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Maintenance mode enabled</info>');

        $ipOption = $input->getOption('ip');
        if ($ipOption !== null) {
            $ips = array_filter(array_map('trim', explode(',', $ipOption)));
            if ($ips) {
                if (file_put_contents($maintenanceIpFile, implode("\n", $ips)) === false) {
                    $output->writeln('<error>Failed to create maintenance.ip file</error>');
                    return Command::FAILURE;
                }
                $output->writeln('<info>Allowed IPs: ' . implode(', ', $ips) . '</info>');
            }
        } elseif (file_exists($maintenanceIpFile)) {
            unlink($maintenanceIpFile);
        }

        return Command::SUCCESS;
    }
}
