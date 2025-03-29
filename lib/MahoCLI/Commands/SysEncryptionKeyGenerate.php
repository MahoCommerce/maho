<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
    name: 'sys:encryptionkey:generate',
    description: 'Generate a new encryption key and save it to local.xml',
)]
class SysEncryptionKeyGenerate extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $key = Mage::generateEncryptionKeyAsHex();

        $localXmlPath = 'app/etc/local.xml';
        $backupPath = 'app/etc/local.xml.bak.' . date('YmdHis');

        // Check if local.xml exists
        if (!file_exists($localXmlPath)) {
            $output->writeln('<error>Configuration file app/etc/local.xml not found</error>');
            return Command::FAILURE;
        }

        // Create backup of local.xml
        if (!copy($localXmlPath, $backupPath)) {
            $output->writeln('<error>Failed to create backup file: ' . $backupPath . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Created backup at: ' . $backupPath . '</info>');

        // Read the current configuration file and replace the encryption key
        $localXmlContent = file_get_contents($localXmlPath);
        $updatedContent = preg_replace(
            '/<key><!\[CDATA\[(.*?)\]\]><\/key>/',
            '<key><![CDATA[' . $key . ']]></key>',
            $localXmlContent
        );

        // Check if replacement was successful
        if ($updatedContent === $localXmlContent && !str_contains($updatedContent, $key)) {
            $output->writeln('<error>Failed to replace encryption key in configuration</error>');
            return Command::FAILURE;
        }

        // Write the updated configuration back to the file
        if (file_put_contents($localXmlPath, $updatedContent) === false) {
            $output->writeln('<error>Failed to write updated configuration</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Encryption key has been successfully updated</info>');
        $output->writeln('<comment>New key: ' . $key . '</comment>');

        return Command::SUCCESS;
    }
}
