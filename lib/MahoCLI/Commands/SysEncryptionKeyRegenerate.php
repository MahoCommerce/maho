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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'sys:encryptionkey:regenerate',
    description: 'Generate a new encryption key and save it to local.xml',
)]
class SysEncryptionKeyRegenerate extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<error>WARNING: This command will replace your encryption key in local.xml</error>');
        $output->writeln('<error>All encrypted data will need to be re-encrypted with the new key.</error>');
        $output->writeln('<error>A backup of local.xml will be created, but please ensure you have a full backup of your database before proceeding.</error>');
        $output->writeln('<error>If possible, encrypted configuration values in core_config_data will be automatically re-encrypted with the new key.</error>');
        $output->writeln('');

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to continue? (y/N) ', false);

        if (!$questionHelper->ask($input, $output, $question)) {
            $output->writeln('<info>Operation cancelled.</info>');
            return Command::SUCCESS;
        }

        $this->initMaho();

        // Disable config cache for current run
        Mage::app()->getCacheInstance()->banUse('config');
        Mage::app()->getConfig()->reinit();

        $oldKey = Mage::getEncryptionKeyAsHex();
        $newKey = Mage::generateEncryptionKeyAsHex();
        $currentDate = date('Y-m-d');
        $localXmlPath = 'app/etc/local.xml';
        $backupPath = 'app/etc/local.xml.bak.' . $currentDate;

        // If it's an M1 encryption key
        if (strlen($oldKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {

        }

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
            '/<key(?:\s+date="[^"]*")?><!\[CDATA\[(.*?)\]\]><\/key>/',
            '<key date="' . $currentDate . '"><![CDATA[' . $newKey . ']]></key>',
            $localXmlContent,
        );

        // Check if replacement was successful
        if ($updatedContent === $localXmlContent && !str_contains($updatedContent, $newKey)) {
            $output->writeln('<error>Failed to replace encryption key in configuration</error>');
            return Command::FAILURE;
        }

        // Write the updated configuration back to the file
        if (file_put_contents($localXmlPath, $updatedContent) === false) {
            $output->writeln('<error>Failed to write updated configuration</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Encryption key has been successfully updated</info>');
        $output->writeln('<comment>New key: ' . $newKey . '</comment>');
        $output->writeln('');

        if (strlen($oldKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {
            $output->writeln('<error>The new key has been generated and saved, but existing encrypted data cannot be automatically migrated.</error>');
            $output->writeln('<error>You will need to manually update any existing encrypted values in the database through the admin panel.</error>');
            return Command::SUCCESS;
        }

        $encryptedPaths = [];
        $sections = Mage::getSingleton('adminhtml/config')->getSections();
        if ($sections) {
            foreach ($sections->children() as $sectionId => $section) {
                if ($section->groups) {
                    foreach ($section->groups->children() as $groupId => $group) {
                        if ($group->fields) {
                            foreach ($group->fields->children() as $fieldId => $field) {
                                if ($field->backend_model && (string) $field->backend_model == 'adminhtml/system_config_backend_encrypted') {
                                    $path = $sectionId . '/' . $groupId . '/' . $fieldId;
                                    $encryptedPaths[] = $path;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($encryptedPaths)) {
            $output->writeln('<info>No encrypted configurations to re-encrypt.</info>');
            return Command::SUCCESS;
        }

        // Re-encrypting encrypted data on core_config_data
        $crypt = Mage::getModel('core/encryption');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');

        $select = $connection->select()
            ->from($table)
            ->where('value IS NOT NULL AND path IN (?)', $encryptedPaths);
        $encryptedData = $connection->fetchAll($select);

        if (empty($encryptedData)) {
            $output->writeln('<info>No encrypted configurations to re-encrypt.</info>');
            return Command::SUCCESS;
        }

        foreach ($encryptedData as &$encryptedDataRow) {
            $encryptedDataRow['value'] = $crypt->decrypt($encryptedDataRow['value']);
        }

        $outputTable = new Table($output);
        $outputTable->setHeaders(['config_id', 'scope', 'scope_id', 'path']);

        Mage::getConfig()->setNode('global/crypt/key', $newKey);
        foreach ($encryptedData as $encryptedDataRow) {
            $newEncryptedValue = $crypt->encrypt($encryptedDataRow['value']);
            $writeConnection->update(
                $table,
                ['value' => $newEncryptedValue],
                ['config_id = ?' => $encryptedDataRow['config_id']],
            );

            $outputTable->addRow([
                $encryptedDataRow['config_id'],
                $encryptedDataRow['scope'],
                $encryptedDataRow['scope_id'],
                $encryptedDataRow['path'],
            ]);
        }

        $output->writeln('<info>The following configurations were just re-encrypted, make sure to test all of them.</info>');
        $outputTable->render();

        Mage::dispatchEvent('encryption_key_regenerated', [
            'old_key' => $oldKey,
            'new_key' => $newKey,
            'output' => $output,
        ]);

        if (\Composer\InstalledVersions::isInstalled('phpseclib/mcrypt_compat')) {
            $output->writeln('');
            $output->writeln('<error>Warning: phpseclib/mcrypt_compat is installed. This package can cause encryption issues and should be removed.</error>');
            $output->writeln('<error>Please remove it using: composer remove phpseclib/mcrypt_compat</error>');
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
