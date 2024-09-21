<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'health-check',
    description: 'Health check your Maho project'
)]
class HealthCheck extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;

        // Check for M1 core files
        $output->write('Checking Magento/OpenMage core... ');
        if (file_exists('app/Mage.php') || file_exists('app/bootstrap.php') || is_dir('app/code/core')) {
            $output->writeln('');
            $output->writeln('<error>Detected files/folder from an old Magento/OpenMage core</error>');
            $output->writeln('Make sure you delete app/bootstrap.php, app/Mage.php and app/code/core,');
            $output->writeln('unless you need to override some specific file from the core (which is unadvisable anyway).');
            $output->writeln('');
        } else {
            $output->writeln('<info>OK</info>');
        }

        // Check for custom API
        $output->write('Checking custom APIs... ');
        exec('grep -ir -l -E "urn:Magento|urn:OpenMage" . --include="*.xml"', $matchingFiles, $returnCode);

        if (empty($matchingFiles)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found "urn:Magento" or "urn:OpenMage" in the following files:</error>');
            foreach ($matchingFiles as $file) {
                $output->writeln('- ' . substr($file, 2));
            }
            $output->writeln('Replace all occurrences of "urn:Magento" or "urn:OpenMage" with "urn:Maho".');
            $output->writeln('');
        }

        // Check for deprecated folders
        $output->write('Checking for deprecated folders... ');
        $deprecatedFolders = [
            'app/code/core/Zend',
            'lib/Cm',
            'lib/Credis',
            'lib/mcryptcompat',
            'lib/Pelago',
            'lib/phpseclib',
            'lib/Zend'
        ];
        $existingDeprecatedFolders = [];
        foreach ($deprecatedFolders as $folder) {
            if (is_dir($folder)) {
                $existingDeprecatedFolders[] = $folder;
            }
        }
        if (empty($existingDeprecatedFolders)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found deprecated folders:</error>');
            foreach ($existingDeprecatedFolders as $folder) {
                $output->writeln('- ' . $folder);
            }
            $output->writeln('You should remove them to avoid unpredictable behaviors.');
            $output->writeln('');
        }

        if ($hasErrors) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
