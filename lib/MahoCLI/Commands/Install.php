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
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'install',
    description: 'Install Maho',
)]
class Install extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'It will accept "yes" value only', name: 'license_agreement_accepted')]
        ?string $licenseAgreementAccepted = null,
        #[Option(description: 'Locale')]
        ?string $locale = null,
        #[Option(description: 'Timezone')]
        ?string $timezone = null,
        #[Option(description: 'Default currency', name: 'default_currency')]
        ?string $defaultCurrency = null,
        #[Option(description: 'You can specify server port (localhost:3307) or UNIX socket (/var/run/mysqld/mysqld.sock)', name: 'db_host')]
        ?string $dbHost = null,
        #[Option(description: 'Database name', name: 'db_name')]
        ?string $dbName = null,
        #[Option(description: 'Database username', name: 'db_user')]
        ?string $dbUser = null,
        #[Option(description: 'Database password', name: 'db_pass')]
        ?string $dbPass = null,
        #[Option(description: 'Database Tables Prefix. No table prefix will be used if not specified', name: 'db_prefix')]
        string $dbPrefix = '',
        #[Option(description: 'Database engine (mysql, pgsql, or sqlite)', name: 'db_engine')]
        string $dbEngine = 'mysql',
        #[Option(description: 'Where to store session data (files/db)', name: 'session_save')]
        string $sessionSave = 'files',
        #[Option(description: 'Admin panel path, "admin" by default', name: 'admin_frontname')]
        string $adminFrontname = 'admin',
        #[Option(description: 'URL the store is supposed to be available at. Ensure the URL ends with a trailing slash (/). For example: http://mydomain.com/maho/')]
        ?string $url = null,
        #[Option(description: 'Use Secure URLs (SSL). Enable this option only if you have SSL available.', name: 'use_secure')]
        bool|string $useSecure = false,
        #[Option(description: 'Secure Base URL. Ensure the URL ends with a trailing slash (/). For example: https://mydomain.com/maho/', name: 'secure_base_url')]
        ?string $secureBaseUrl = null,
        #[Option(description: 'Run admin interface with SSL', name: 'use_secure_admin')]
        bool|string $useSecureAdmin = false,
        #[Option(description: 'Admin user last name', name: 'admin_lastname')]
        ?string $adminLastname = null,
        #[Option(description: 'Admin user first name', name: 'admin_firstname')]
        ?string $adminFirstname = null,
        #[Option(description: 'Admin user email', name: 'admin_email')]
        ?string $adminEmail = null,
        #[Option(description: 'Admin user login', name: 'admin_username')]
        ?string $adminUsername = null,
        #[Option(description: 'Admin user password', name: 'admin_password')]
        ?string $adminPassword = null,
        #[Option(description: 'Also install sample data: 1 downloads the branch of this version, a path uses a local package folder', name: 'sample_data')]
        ?string $sampleData = null,
        #[Option(description: 'Force reinstallation - drops database and removes local.xml')]
        bool $force = false,
    ): int {
        if ($force) {
            if (!$this->handleForceInstall($output, $dbHost, $dbName, $dbUser, $dbPass, $dbEngine)) {
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

        $this->showLocalizationSuggestions($locale, $output);

        $output->writeln('');

        if ($sampleData) {
            return $this->installSampleData($sampleData, $output);
        }

        return Command::SUCCESS;
    }

    private function showLocalizationSuggestions(?string $locale, OutputInterface $output): void
    {
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

    private function handleForceInstall(
        OutputInterface $output,
        ?string $dbHost,
        ?string $dbName,
        ?string $dbUser,
        #[\SensitiveParameter]
        ?string $dbPass,
        string $dbEngine,
    ): bool {
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
        // The install ran on an app booted before local.xml existed; boot again so stores and config are live
        Mage::reset();
        Mage::app(\Mage_Core_Model_Store::ADMIN_CODE, 'store');
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
            $result = new SampleDataInstaller($reporter)->install($package, null, false);
        } catch (\Exception $e) {
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
