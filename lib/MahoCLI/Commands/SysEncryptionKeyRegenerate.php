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
            '<key date="' . substr($currentDate, 0, 10) . '"><![CDATA[' . $newKey . ']]></key>',
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

        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        if ($this->isOldEncryptionKeyM1 && !function_exists('mcrypt_module_open')) {
            $output->writeln('<comment>Skipping re-encryption of existing data (M1 key without mcrypt support).</comment>');
            $output->writeln('<comment>Old encrypted data will no longer be decryptable. New data will use the new key.</comment>');
        } else {
            $this->recryptAdminUserTable($output);
            $this->recryptCoreConfigDataTable($output, $readConnection, $writeConnection);
            Mage::app()->getCache()->clean('config');

            Mage::dispatchEvent('encryption_key_regenerated', [
                'output' => $output,
                'encrypt_callback' => [$this, 'encrypt'],
                'decrypt_callback' => [$this, 'decrypt'],
            ]);
            Mage::app()->getCache()->clean('config');
        }

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

    protected function recryptAdminUserTable(OutputInterface $output): void
    {
        $output->write('Re-encrypting data on admin_user table... ');
        Mage::helper('core')->recryptTable(
            Mage::getSingleton('core/resource')->getTableName('admin_user'),
            'user_id',
            ['twofa_secret'],
            [$this, 'encrypt'],
            [$this, 'decrypt'],
        );
        $output->writeln('OK');
    }

    protected function recryptCoreConfigDataTable(OutputInterface $output, \Maho\Db\Adapter\AdapterInterface $readConnection, \Maho\Db\Adapter\AdapterInterface $writeConnection): void
    {
        // Checking if there are any encrypted configurations that should be re-encrypted
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
