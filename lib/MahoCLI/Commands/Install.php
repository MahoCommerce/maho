<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Exception;
use Locale;
use Mage;
use Mage_Install_Model_Installer_Console;
use Maho\Import\SampleData\Installer as SampleDataInstaller;
use Maho\Import\SampleData\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'install',
    description: 'Install Maho',
)]
class Install extends BaseMahoCommand
{
    use ImportCommandTrait;

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
        $this->addOption('db_engine', null, InputOption::VALUE_OPTIONAL, 'Database engine (mysql, pgsql, or sqlite)', 'mysql');

        // Session options
        $this->addOption('session_save', null, InputOption::VALUE_OPTIONAL, 'Where to store session data (files/db)', 'files');

        // Web access options
        $this->addOption('admin_frontname', null, InputOption::VALUE_OPTIONAL, 'Admin panel path, "admin" by default', 'admin');
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL the store is supposed to be available at. Ensure the URL ends with a trailing slash (/). For example: http://mydomain.com/maho/');
        $this->addOption('use_secure', null, InputOption::VALUE_OPTIONAL, 'Use Secure URLs (SSL). Enable this option only if you have SSL available.', false);
        $this->addOption('secure_base_url', null, InputOption::VALUE_OPTIONAL, 'Secure Base URL. Ensure the URL ends with a trailing slash (/). For example: https://mydomain.com/maho/');
        $this->addOption('use_secure_admin', null, InputOption::VALUE_OPTIONAL, 'Run admin interface with SSL', false);

        // Admin user personal information
        $this->addOption('admin_lastname', null, InputOption::VALUE_REQUIRED, 'Admin user last name');
        $this->addOption('admin_firstname', null, InputOption::VALUE_REQUIRED, 'Admin user first name');
        $this->addOption('admin_email', null, InputOption::VALUE_REQUIRED, 'Admin user email');

        // Admin user login information
        $this->addOption('admin_username', null, InputOption::VALUE_REQUIRED, 'Admin user login');
        $this->addOption('admin_password', null, InputOption::VALUE_REQUIRED, 'Admin user password');

        // Sample data
        $this->addOption('sample_data', null, InputOption::VALUE_OPTIONAL, 'Also install sample data: 1 downloads the branch of this version, a path uses a local package folder');

        // Force option
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force reinstallation - drops database and removes local.xml');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Handle force option
        if ($input->getOption('force')) {
            if (!$this->handleForceInstall($input, $output)) {
                return Command::SUCCESS;
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

        $this->showLocalizationSuggestions($input, $output);

        $output->writeln('');

        $sampleData = $input->getOption('sample_data');
        if ($sampleData) {
            return $this->installSampleData((string) $sampleData, $output);
        }

        return Command::SUCCESS;
    }

    private function showLocalizationSuggestions(InputInterface $input, OutputInterface $output): void
    {
        $locale = $input->getOption('locale');

        if (!$locale || $locale === 'en_US') {
            return;
        }

        $parsed = Locale::parseLocale($locale);
        $countryCode = $parsed['region'] ?? null;

        if (!$countryCode) {
            return;
        }

        $countryName = Locale::getDisplayRegion($locale, 'en');
        $languageName = Locale::getDisplayLanguage($locale, 'en');

        $output->writeln('');
        $output->writeln('<info>  Localization recommendations for your store</info>');
        $output->writeln('');
        $output->writeln("  Your store locale is set to <comment>{$locale}</comment>. To fully localize your");
        $output->writeln('  store, we recommend running the following commands:');
        $output->writeln('');
        $output->writeln("  Import regions/states for {$countryName}:");
        $output->writeln("    <comment>./maho sys:directory:regions:import -c {$countryCode} -l {$locale}</comment>");

        if (in_array($locale, \Mage_Install_Helper_Data::AVAILABLE_LANGUAGE_PACKS, true)) {
            $packageName = 'mahocommerce/maho-language-' . strtolower($locale);
            $output->writeln('');
            $output->writeln("  Install the {$languageName} language pack:");
            $output->writeln("    <comment>composer require {$packageName}</comment>");
        }

        $output->writeln('');
    }

    private function handleForceInstall(InputInterface $input, OutputInterface $output): bool
    {
        $output->writeln('<comment>Force installation requested - clearing existing installation...</comment>');

        $localXmlPath = getcwd() . '/app/etc/local.xml';
        if (file_exists($localXmlPath)) {
            // Flush the configured cache backend (could be Redis/Memcached, not
            // just files on disk) using the existing installation's config,
            // before local.xml is removed. The install bootstraps before
            // installDb runs, so stale cached config would make it query tables
            // on the now-empty database before they are recreated. Best-effort:
            // a prior install too broken to boot must not block the reinstall.
            // Mage::reset() then leaves a clean slate for the installer's own
            // bootstrap.
            try {
                Mage::app()->getCache()->flush();
                $output->writeln('<info>Flushed existing cache</info>');
            } catch (\Throwable) {
                // ignore: the prior install may be unbootable
            } finally {
                Mage::reset();
            }

            if (is_writable($localXmlPath)) {
                unlink($localXmlPath);
                $output->writeln('<info>Removed existing local.xml</info>');
            } else {
                $output->writeln('<error>Cannot remove local.xml - file is not writable</error>');
                throw new \RuntimeException('Cannot remove local.xml - insufficient permissions');
            }
        }

        // Clear all tables in the database
        $dbHost = $input->getOption('db_host');
        $dbName = $input->getOption('db_name');
        $dbUser = $input->getOption('db_user');
        $dbPass = $input->getOption('db_pass');
        $dbEngine = $input->getOption('db_engine') ?? 'mysql';

        // Handle SQLite separately - just delete the database file
        if ($dbEngine === 'sqlite') {
            $dbPath = getcwd() . '/var/db/' . $dbName;
            if (file_exists($dbPath)) {
                if (is_writable($dbPath)) {
                    unlink($dbPath);
                    $output->writeln('<info>Removed existing SQLite database</info>');
                } else {
                    $output->writeln('<error>Cannot remove SQLite database - file is not writable</error>');
                    throw new \RuntimeException('Cannot remove SQLite database - insufficient permissions');
                }
            } else {
                $output->writeln('<info>SQLite database does not exist yet</info>');
            }
        } elseif ($dbHost && $dbName && $dbUser !== null) {
            try {
                $isPostgres = ($dbEngine === 'pgsql');
                if ($isPostgres) {
                    $dsn = "pgsql:host={$dbHost};dbname={$dbName}";
                } else {
                    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8";
                }
                $pdo = new \PDO($dsn, $dbUser, $dbPass);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                if ($isPostgres) {
                    // PostgreSQL: Get all tables and drop them with CASCADE
                    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                    if (count($tables) > 0) {
                        $output->writeln('<comment>Found ' . count($tables) . ' tables to remove...</comment>');

                        // Drop all tables with CASCADE to handle foreign keys
                        foreach ($tables as $table) {
                            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
                        }

                        $output->writeln('<info>Cleared all tables from the database</info>');
                    } else {
                        $output->writeln('<info>Database is already empty</info>');
                    }
                } else {
                    // MySQL: Disable foreign key checks and drop tables
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

                    $stmt = $pdo->query('SHOW TABLES');
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                    if (count($tables) > 0) {
                        $output->writeln('<comment>Found ' . count($tables) . ' tables to remove...</comment>');

                        foreach ($tables as $table) {
                            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                        }

                        $output->writeln('<info>Cleared all tables from the database</info>');
                    } else {
                        $output->writeln('<info>Database is already empty</info>');
                    }

                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                }

            } catch (\PDOException $e) {
                $output->writeln("<error>Failed to clear database: {$e->getMessage()}</error>");

                // If it's a connection error, provide more helpful message
                if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), 'does not exist')) {
                    $output->writeln("<error>Database '{$dbName}' does not exist. Please create it first.</error>");
                } elseif (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), 'authentication failed')) {
                    $output->writeln('<error>Access denied. Please check your database credentials.</error>');
                }

                throw $e;
            }
        }

        $output->writeln('<info>Force preparation completed</info>');
        return true;
    }

    /**
     * "1" or "yes" downloads the branch of this Maho version; any other value is a local package folder.
     */
    private function installSampleData(string $source, OutputInterface $output): int
    {
        $reporter = $this->consoleReporter($output, false);
        try {
            if (in_array(strtolower($source), ['1', 'yes', 'true'], true)) {
                $package = Package::forBranch(Package::branchForVersion(Mage::getVersion()), $reporter->info(...));
            } else {
                $package = Package::fromPath($source);
            }
        } catch (\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Installing sample data</info>');
        try {
            $result = (new SampleDataInstaller($reporter))->install($package, null, false);
        } catch (\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $package->cleanup();
        }
        $output->writeln('<info>Sample data installed: ' . $result->summary() . '</info>');
        $output->writeln('<info>Please run ./maho index:reindex:all && ./maho cache:flush</info>');
        return Command::SUCCESS;
    }
}
