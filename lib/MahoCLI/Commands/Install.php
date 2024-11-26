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

use Exception;
use Mage;
use Mage_Install_Model_Installer_Console;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'install',
    description: 'Install Maho'
)]
class Install extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        // License
        $this->addOption('license_agreement_accepted', null, InputOption::VALUE_REQUIRED, 'It will accept "yes" value only');

        // Locale options
        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale');
        $this->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone');
        $this->addOption('default_currency', null, InputOption::VALUE_REQUIRED, 'Default currency');

        // Database connection options
        $this->addOption('db_host', null, InputOption::VALUE_REQUIRED, 'You can specify server port (localhost:3307) or UNIX socket (/var/run/mysqld/mysqld.sock)');
        $this->addOption('db_name', null, InputOption::VALUE_REQUIRED, 'Database name');
        $this->addOption('db_user', null, InputOption::VALUE_REQUIRED, 'Database username');
        $this->addOption('db_pass', null, InputOption::VALUE_REQUIRED, 'Database password');
        $this->addOption('db_prefix', null, InputOption::VALUE_OPTIONAL, 'Database Tables Prefix. No table prefix will be used if not specified', '');

        // Session options
        $this->addOption('session_save', null, InputOption::VALUE_OPTIONAL, 'Where to store session data (files/db)', 'files');

        // Web access options
        $this->addOption('admin_frontname', null, InputOption::VALUE_OPTIONAL, 'Admin panel path, "admin" by default', 'admin');
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL the store is supposed to be available at');
        $this->addOption('use_secure', null, InputOption::VALUE_OPTIONAL, 'Use Secure URLs (SSL). Enable this option only if you have SSL available.', false);
        $this->addOption('secure_base_url', null, InputOption::VALUE_OPTIONAL, 'Secure Base URL. Provide a complete base URL for SSL connection. For example: https://mydomain.com/');
        $this->addOption('use_secure_admin', null, InputOption::VALUE_OPTIONAL, 'Run admin interface with SSL', false);

        // Admin user personal information
        $this->addOption('admin_lastname', null, InputOption::VALUE_REQUIRED, 'Admin user last name');
        $this->addOption('admin_firstname', null, InputOption::VALUE_REQUIRED, 'Admin user first name');
        $this->addOption('admin_email', null, InputOption::VALUE_REQUIRED, 'Admin user email');

        // Admin user login information
        $this->addOption('admin_username', null, InputOption::VALUE_REQUIRED, 'Admin user login');
        $this->addOption('admin_password', null, InputOption::VALUE_REQUIRED, 'Admin user password');

        // Encryption key
        $this->addOption('encryption_key', null, InputOption::VALUE_OPTIONAL, 'Will be automatically generated and displayed on success, if not specified');

        // Sample data
        $this->addOption('sample_data', null, InputOption::VALUE_OPTIONAL, 'Also install sample data');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Reset some options in case we're installing sample data
        if ($input->getOption('sample_data')) {
            $options = $input->getOptions();
            $options['locale'] = 'en_US';
            $options['default_currency'] = 'USD';
            unset($options['db_prefix']);

            $_SERVER['argv'] = ['maho', 'install'];
            foreach ($options as $key => $value) {
                $_SERVER['argv'][] = "--{$key}";
                $_SERVER['argv'][] = $value;
            }
        }

        $this->initMaho();

        array_shift($_SERVER['argv']);
        array_shift($_SERVER['argv']);

        /** @var Mage_Install_Model_Installer_Console $installer */
        $installer = Mage::getSingleton('install/installer_console');

        try {
            $app = Mage::app('default');
            if ($installer->init($app) && $installer->setArgs() && $installer->install()) {
                $output->writeln('<info>Installation completed successfully</info>');
                $output->writeln("The encryption key for your installation is {$installer->getEncryptionKey()}");
            }
        } catch (Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($installer->getErrors()) {
            foreach ($installer->getErrors() as $error) {
                $output->writeln("<error>{$error}</error>");
            }
            return Command::FAILURE;
        }

        $output->writeln('');

        // Download and decompress sample data
        if ($input->getOption('sample_data')) {
            $output->writeln('<info>Downloading sample data...</info>');

            $sampleDataUrl = 'https://github.com/MahoCommerce/maho-sample-data/archive/refs/heads/main.tar.gz';
            $tempFile = tempnam(sys_get_temp_dir(), 'maho_sample_data');
            $targetDir = Mage::getBaseDir();

            // Download the file
            if (file_put_contents($tempFile, file_get_contents($sampleDataUrl)) === false) {
                $output->writeln('<error>Failed to download sample data</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>Extracting and copying sample data files...</info>');

            // Extract the archive using tar
            $extractCommand = "tar -xzf $tempFile -C $targetDir";
            exec($extractCommand, $extractOutput, $extractReturnVar);

            if ($extractReturnVar !== 0) {
                $output->writeln("<error>Failed to extract sample data. tar command returned: $extractReturnVar</error>");
                foreach ($extractOutput as $line) {
                    $output->writeln($line);
                }
                return Command::FAILURE;
            }

            // Copy media files
            $sourceMediaDir = $targetDir . '/maho-sample-data-main/media';
            $targetMediaDir = $targetDir . '/public/media';

            $copyCommand = "cp -R $sourceMediaDir/* $targetMediaDir/";
            exec($copyCommand, $copyOutput, $copyReturnVar);

            if ($copyReturnVar !== 0) {
                $output->writeln("<error>Failed to copy media files. cp command returned: $copyReturnVar</error>");
                foreach ($copyOutput as $line) {
                    $output->writeln($line);
                }
                return Command::FAILURE;
            }

            $output->writeln('<info>Installing sample database</info>');

            $dbHost = $input->getOption('db_host');
            $dbName = $input->getOption('db_name');
            $dbUser = $input->getOption('db_user');
            $dbPass = $input->getOption('db_pass');
            $sqlFiles = ['db_preparation.sql', 'db_data.sql'];

            // Create a temporary MySQL configuration file
            $sampleDataDir = $targetDir . '/maho-sample-data-main';
            $tmpMyCnf = $sampleDataDir . '/temp_my.cnf';
            file_put_contents($tmpMyCnf, "[client]\nuser={$dbUser}\npassword={$dbPass}\nhost={$dbHost}\n");
            chmod($tmpMyCnf, 0600);

            foreach ($sqlFiles as $sqlFile) {
                $sqlFilePath = $sampleDataDir . '/' . $sqlFile;
                $importCommand = 'mysql --defaults-extra-file=' . escapeshellarg($tmpMyCnf) . " {$dbName} < " . escapeshellarg($sqlFilePath);
                exec($importCommand, $importOutput, $importReturnVar);

                if ($importReturnVar !== 0) {
                    $output->writeln("<error>Failed to import {$sqlFile}. mysql command returned: $importReturnVar</error>");
                    $output->writeln('<error>Error output:</error>');
                    foreach ($importOutput as $line) {
                        $output->writeln($line);
                    }
                    unlink($tmpMyCnf);  // Remove the temporary configuration file
                    return Command::FAILURE;
                }
            }

            $output->writeln('<info>Sample data, media files, and database content installed successfully</info>');
            $output->writeln('<info>Please run ./maho index:reindex:all and ./maho cache:flush</info>');

            // Clean up
            unlink($tempFile);
            $rmCommand = 'rm -rf ' . escapeshellarg($targetDir . '/maho-sample-data-main');
            exec($rmCommand, $rmOutput, $rmReturnVar);

            if ($rmReturnVar !== 0) {
                $output->writeln("<error>Failed to remove temporary files. rm command returned: $rmReturnVar</error>");
                foreach ($rmOutput as $line) {
                    $output->writeln($line);
                }
                // We don't return FAILURE here as the installation itself was successful
            }
        }

        return Command::SUCCESS;
    }
}
