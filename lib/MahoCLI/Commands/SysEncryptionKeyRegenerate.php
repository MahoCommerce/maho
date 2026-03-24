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
    private string $oldEncryptionKey;
    private string $newEncryptionKey;
    private bool $isOldEncryptionKeyM1 = false;

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
        Mage::app()->getCache()->banUse('config');
        Mage::app()->getConfig()->reinit();

        $oldKey = $this->oldEncryptionKey = Mage::getEncryptionKeyAsHex();
        $newKey = $this->newEncryptionKey = Mage::generateEncryptionKeyAsHex();
        $currentDate = date('Y-m-d-H-i-s');
        $localXmlPath = 'app/etc/local.xml';
        $backupPath = 'app/etc/local.xml.bak.' . $currentDate;

        // If it's an M1 encryption key check for mcrypt_compat
        if (strlen($oldKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {
            $this->isOldEncryptionKeyM1 = true;
            $output->writeln('');
            $output->writeln('<error>It seems your encryption key is an old M1 one.</error>');
            if (\Composer\InstalledVersions::isInstalled('phpseclib/mcrypt_compat')) {
                $output->writeln('<error>Since you have phpseclib/mcrypt_compat installed we will try to re-encrypt all your crypted data.</error>');
            } else {
                $output->writeln('<error>Since you do not have phpseclib/mcrypt_compat installed we will not be able to re-encrypt your crypted data.</error>');
            }

            $output->writeln('');
            $question = new ConfirmationQuestion('Are you sure you want to continue? (y/N) ', false);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<info>Operation cancelled.</info>');
                return Command::SUCCESS;
            }
        }

        // Check if local.xml exists
        if (!file_exists($localXmlPath)) {
            $output->writeln('<error>Configuration file app/etc/local.xml not found</error>');
            return Command::FAILURE;
        }

        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        if ($this->isOldEncryptionKeyM1 && !function_exists('mcrypt_module_open')) {
            $output->writeln('<comment>Skipping re-encryption of existing data (M1 key without mcrypt support).</comment>');
            $output->writeln('<comment>Old encrypted data will no longer be decryptable. New data will use the new key.</comment>');
        } else {
            // Phase 1: Validate all encrypted data can be decrypted before making any changes
            $output->writeln('');
            $output->writeln('<info>Validating all encrypted data can be decrypted with the current key...</info>');

            $allFailures = $this->validateAllEncryptedData($output, $readConnection);

            if (!empty($allFailures)) {
                $output->writeln('');
                $output->writeln('<error>Decryption validation failed! The following values could not be decrypted:</error>');
                $failureTable = new Table($output);
                $failureTable->setHeaders(['Table', 'Primary Key', 'Column']);
                foreach ($allFailures as $failure) {
                    $failureTable->addRow([$failure['table'], $failure['primary_key'], $failure['column']]);
                }
                $failureTable->render();
                $output->writeln('');
                $output->writeln('<error>Aborting key regeneration. No changes were made.</error>');
                $output->writeln('<comment>Fix the undecryptable values above before retrying.</comment>');
                return Command::FAILURE;
            }

            $output->writeln('<info>All encrypted data validated successfully.</info>');
            $output->writeln('');

            // Phase 2: Re-encrypt all data within a transaction
            $output->writeln('<info>Re-encrypting all data...</info>');
            $writeConnection->beginTransaction();
            try {
                $this->recryptAdminUserTable($output);
                $this->recryptCoreConfigDataTable($output, $readConnection, $writeConnection);

                Mage::dispatchEvent('encryption_key_regenerated', [
                    'output' => $output,
                    'encrypt_callback' => [$this, 'encrypt'],
                    'decrypt_callback' => [$this, 'decrypt'],
                ]);

                $writeConnection->commit();
            } catch (\Throwable $e) {
                $writeConnection->rollBack();
                $output->writeln('');
                $output->writeln('<error>Re-encryption failed, transaction rolled back. No changes were made.</error>');
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            Mage::app()->getCache()->clean('config');
        }

        // Phase 3: Update local.xml only after successful DB re-encryption
        if (!copy($localXmlPath, $backupPath)) {
            $output->writeln('<error>Failed to create backup file: ' . $backupPath . '</error>');
            $output->writeln('<error>Database was already re-encrypted. You must manually update the key in local.xml.</error>');
            $output->writeln('<comment>New key: ' . $newKey . '</comment>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Created backup at: ' . $backupPath . '</info>');

        $localXmlContent = file_get_contents($localXmlPath);
        $updatedContent = preg_replace(
            '/<key(?:\s+date="[^"]*")?><!\[CDATA\[(.*?)\]\]><\/key>/',
            '<key date="' . substr($currentDate, 0, 10) . '"><![CDATA[' . $newKey . ']]></key>',
            $localXmlContent,
        );

        if ($updatedContent === $localXmlContent && !str_contains($updatedContent, $newKey)) {
            $output->writeln('<error>Failed to replace encryption key in configuration</error>');
            $output->writeln('<error>Database was already re-encrypted. You must manually update the key in local.xml.</error>');
            $output->writeln('<comment>New key: ' . $newKey . '</comment>');
            return Command::FAILURE;
        }

        if (file_put_contents($localXmlPath, $updatedContent) === false) {
            $output->writeln('<error>Failed to write updated configuration</error>');
            $output->writeln('<error>Database was already re-encrypted. You must manually update the key in local.xml.</error>');
            $output->writeln('<comment>New key: ' . $newKey . '</comment>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Encryption key has been successfully updated</info>');
        $output->writeln('<comment>New key: ' . $newKey . '</comment>');
        $output->writeln('');

        if (\Composer\InstalledVersions::isInstalled('mahocommerce/module-mcrypt-compat')) {
            $output->writeln('');
            $output->writeln('<error>You may now remove the compatibility module using: composer remove mahocommerce/module-mcrypt-compat</error>');
            $output->writeln('Then check if you still have phpseclib/mcrypt_compat installed and evaluate its removal too.');
            $output->writeln('');
        } elseif (\Composer\InstalledVersions::isInstalled('phpseclib/mcrypt_compat')) {
            $output->writeln('');
            $output->writeln('<error>Warning: phpseclib/mcrypt_compat is installed. This package is obsolete and should be removed.</error>');
            $output->writeln('If directly installed, remove it with <info>composer remove phpseclib/mcrypt_compat</info>.');
            $output->writeln('If installed as a dependency, find which package requires it with <info>composer why phpseclib/mcrypt_compat</info> and evaluate its removal.');
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{table: string, primary_key: mixed, column: string}>
     */
    protected function validateAllEncryptedData(OutputInterface $output, \Maho\Db\Adapter\AdapterInterface $readConnection): array
    {
        $helper = Mage::helper('core');
        $allFailures = [];

        $tablesToValidate = [
            ['table' => 'admin_user', 'pk' => 'user_id', 'columns' => ['twofa_secret']],
            ['table' => 'sales_flat_quote_payment', 'pk' => 'payment_id', 'columns' => ['cc_number_enc', 'cc_cid_enc']],
            ['table' => 'sales_flat_order_payment', 'pk' => 'entity_id', 'columns' => ['cc_number_enc']],
            ['table' => 'sales_flat_quote', 'pk' => 'entity_id', 'columns' => ['password_hash']],
            ['table' => 'maho_paypal/vault_token', 'pk' => 'token_id', 'columns' => ['paypal_token_id']],
            ['table' => 'feedmanager/destination', 'pk' => 'destination_id', 'columns' => ['config']],
            ['table' => 'adminactivitylog/activity', 'pk' => 'activity_id', 'columns' => ['old_data', 'new_data']],
        ];

        foreach ($tablesToValidate as $tableInfo) {
            try {
                $tableName = Mage::getSingleton('core/resource')->getTableName($tableInfo['table']);
            } catch (\Mage_Core_Exception) {
                $output->writeln('Skipping ' . $tableInfo['table'] . ' (module not installed)');
                continue;
            }
            $output->write("Validating $tableName table... ");
            $failures = $helper->validateDecryptTable(
                $tableName,
                $tableInfo['pk'],
                $tableInfo['columns'],
                [$this, 'decrypt'],
            );
            $output->writeln(empty($failures) ? 'OK' : '<error>' . count($failures) . ' failure(s)</error>');
            $allFailures = array_merge($allFailures, $failures);
        }

        // Validate core_config_data
        $encryptedPaths = $this->getEncryptedConfigPaths();
        if (!empty($encryptedPaths)) {
            $output->write('Validating core_config_data table... ');
            $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');
            $select = $readConnection->select()
                ->from($table)
                ->where('value IS NOT NULL AND path IN (?)', $encryptedPaths);
            $encryptedData = $readConnection->fetchAll($select);

            $configFailures = 0;
            foreach ($encryptedData as $row) {
                $decrypted = $this->decrypt($row['value']);
                if ($decrypted === '') {
                    $configFailures++;
                    $allFailures[] = [
                        'table' => $table,
                        'primary_key' => $row['config_id'],
                        'column' => 'value (' . $row['path'] . ')',
                    ];
                }
            }
            $output->writeln($configFailures === 0 ? 'OK' : '<error>' . $configFailures . ' failure(s)</error>');
        }

        return $allFailures;
    }

    /**
     * @return string[]
     */
    protected function getEncryptedConfigPaths(): array
    {
        $encryptedPaths = [];
        $sections = Mage::getSingleton('adminhtml/config')->getSections();
        if ($sections) {
            foreach ($sections->children() as $sectionId => $section) {
                if ($section->groups) {
                    foreach ($section->groups->children() as $groupId => $group) {
                        if ($group->fields) {
                            foreach ($group->fields->children() as $fieldId => $field) {
                                if ($field->backend_model && (string) $field->backend_model == 'adminhtml/system_config_backend_encrypted') {
                                    $encryptedPaths[] = $sectionId . '/' . $groupId . '/' . $fieldId;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $encryptedPaths;
    }

    protected function recryptAdminUserTable(OutputInterface $output): void
    {
        $output->write('Re-encrypting data on admin_user table... ');
        $result = Mage::helper('core')->recryptTable(
            Mage::getSingleton('core/resource')->getTableName('admin_user'),
            'user_id',
            ['twofa_secret'],
            [$this, 'encrypt'],
            [$this, 'decrypt'],
            output: $output,
        );
        $output->writeln($result ? 'OK' : '<comment>SKIPPED</comment>');
    }

    protected function recryptCoreConfigDataTable(OutputInterface $output, \Maho\Db\Adapter\AdapterInterface $readConnection, \Maho\Db\Adapter\AdapterInterface $writeConnection): void
    {
        $encryptedPaths = $this->getEncryptedConfigPaths();

        if (empty($encryptedPaths)) {
            $output->writeln('<info>No encrypted configurations to re-encrypt.</info>');
            return;
        }

        // Re-encrypting encrypted data on core_config_data
        $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');
        $select = $readConnection->select()
            ->from($table)
            ->where('value IS NOT NULL AND path IN (?)', $encryptedPaths);
        $encryptedData = $readConnection->fetchAll($select);

        if (empty($encryptedData)) {
            $output->writeln('<info>No encrypted configurations to re-encrypt.</info>');
            return;
        }

        $outputTable = new Table($output);
        $outputTable->setHeaders(['config_id', 'scope', 'scope_id', 'path']);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['value' => $this->encrypt($this->decrypt($encryptedDataRow['value']))],
                ['config_id = ?' => $encryptedDataRow['config_id']],
            );

            $outputTable->addRow([
                $encryptedDataRow['config_id'],
                $encryptedDataRow['scope'],
                $encryptedDataRow['scope_id'],
                $encryptedDataRow['path'],
            ]);
        }

        $output->writeln('');
        $output->writeln('<info>The following configurations were just re-encrypted, make sure to test all of them.</info>');
        $outputTable->render();
        $output->writeln('');
    }

    public function decrypt(#[\SensitiveParameter] string $data): string
    {
        if ($this->isOldEncryptionKeyM1) {
            if (!function_exists('mcrypt_module_open')) {
                return '';
            }

            $key = $this->oldEncryptionKey;
            $handler = mcrypt_module_open(MCRYPT_BLOWFISH, '', MCRYPT_MODE_ECB, ''); // @phpstan-ignore constant.notFound,constant.notFound
            $initVector = mcrypt_create_iv(mcrypt_enc_get_iv_size($handler), MCRYPT_RAND); // @phpstan-ignore function.notFound,function.notFound,constant.notFound
            mcrypt_generic_init($handler, $key, $initVector); // @phpstan-ignore function.notFound
            $data = mdecrypt_generic($handler, (string) base64_decode((string) $data)); // @phpstan-ignore function.notFound
            mcrypt_generic_deinit($handler); // @phpstan-ignore function.notFound
            mcrypt_module_close($handler); // @phpstan-ignore function.notFound
            return str_replace("\x0", '', trim($data));
        }

        try {
            $decoded = sodium_base642bin($data, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException $e) {
            $exception = new \Exception('Invalid base64 encoding: ' . $e->getMessage());
            Mage::logException($exception);
            return '';
        }

        $key = sodium_hex2bin($this->oldEncryptionKey);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        // Clean sensitive data from memory
        sodium_memzero($data);
        sodium_memzero($decoded);
        sodium_memzero($key);
        sodium_memzero($nonce);
        sodium_memzero($ciphertext);

        return (string) $plaintext;
    }

    public function encrypt(#[\SensitiveParameter] string $data): string
    {
        $key = sodium_hex2bin($this->newEncryptionKey);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($data, $nonce, $key);
        $encrypted = sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL);

        // Clean sensitive data from memory
        sodium_memzero($data);
        sodium_memzero($key);
        sodium_memzero($nonce);
        sodium_memzero($ciphertext);

        return $encrypted;
    }
}
