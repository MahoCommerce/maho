<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Exception;
use Locale;
use Mage;
use Mage_Install_Model_Installer_Console;
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
        $this->addOption('sample_data', null, InputOption::VALUE_OPTIONAL, 'Also install sample data');

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

        // Download and decompress sample data
        if ($input->getOption('sample_data')) {
            $output->writeln('<info>Downloading sample data...</info>');

            // Get Maho version and determine the corresponding branch
            $mahoVersion = Mage::getVersion(); // e.g., "25.9.0"
            $versionParts = explode('.', $mahoVersion);
            $branchVersion = "{$versionParts[0]}.{$versionParts[1]}"; // e.g., "25.9"

            $sampleDataUrl = "https://github.com/MahoCommerce/maho-sample-data/archive/refs/heads/{$branchVersion}.tar.gz";
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
            $sampleDataDirName = "maho-sample-data-{$branchVersion}";
            $sourceMediaDir = $targetDir . "/{$sampleDataDirName}/media";
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
            $dbEngine = $input->getOption('db_engine') ?? 'mysql';
            $sampleDataDir = $targetDir . "/{$sampleDataDirName}";

            try {
                // Create PDO connection based on database engine
                if ($dbEngine === 'pgsql') {
                    $dsn = "pgsql:host={$dbHost};dbname={$dbName}";
                    $pdo = new \PDO($dsn, $dbUser, $dbPass);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->exec('SET session_replication_role = replica');
                } elseif ($dbEngine === 'sqlite') {
                    $dbPath = $dbName;
                    if ($dbPath[0] !== '/' && !str_contains($dbPath, ':')) {
                        $baseDir = defined('BP') ? BP : getcwd();
                        $dbDir = $baseDir . '/var/db';
                        if (!is_dir($dbDir)) {
                            mkdir($dbDir, 0755, true);
                        }
                        $dbPath = $dbDir . '/' . $dbPath;
                    }
                    $dsn = "sqlite:{$dbPath}";
                    $pdo = new \PDO($dsn);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                } else {
                    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8";
                    $pdo = new \PDO($dsn, $dbUser, $dbPass);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                }

                // Import db_data.sql with attribute ID remapping
                $dataFilePath = $sampleDataDir . '/db_data.sql';
                if (file_exists($dataFilePath)) {
                    $output->writeln('<info>Importing db_data.sql with attribute ID remapping...</info>');
                    $dataSql = file_get_contents($dataFilePath);

                    // Create logger callback for the importer
                    $logCallback = function (string $message, string $level = 'info') use ($output) {
                        $tag = match ($level) {
                            'error' => 'error',
                            'warning' => 'comment',
                            default => 'info',
                        };
                        $output->writeln("  <{$tag}>{$message}</{$tag}>");
                    };

                    $importer = new \MahoCLI\Helper\SampleDataImporter($pdo, $logCallback);
                    $output->writeln('  Parsing attribute mappings...');
                    $remappedSql = $importer->import($dataSql);

                    $attrRemap = $importer->getAttributeRemap();
                    $output->writeln('  Remapped ' . count($attrRemap) . ' attributes');

                    // Execute the remapped SQL
                    $output->writeln('  Executing remapped SQL...');
                    $this->executeSqlForEngine($pdo, $remappedSql, $dbEngine, $output);

                    // Merge attribute groups (creates new ones, builds group ID remap)
                    $importer->mergeAttributeGroups();

                    // Merge sample data's attribute set assignments (adds new ones, preserves existing)
                    $importer->mergeEntityAttributes();

                    $output->writeln('<info>Successfully imported db_data.sql</info>');

                    // Import db_config.sql with config value remapping
                    $configFilePath = $sampleDataDir . '/db_config.sql';
                    if (file_exists($configFilePath)) {
                        $output->writeln('<info>Importing db_config.sql with config remapping...</info>');
                        $configSql = file_get_contents($configFilePath);

                        // Remap attribute IDs in config values (like configswatches)
                        $remappedConfigSql = $importer->remapConfigValuesOnly($configSql);

                        $this->executeSqlForEngine($pdo, $remappedConfigSql, $dbEngine, $output);
                        $output->writeln('<info>Successfully imported db_config.sql</info>');
                    }
                }

                // PostgreSQL post-processing
                if ($dbEngine === 'pgsql') {
                    $pdo->exec('SET session_replication_role = DEFAULT');
                    $output->writeln('<info>Updating PostgreSQL sequences...</info>');
                    $this->updatePostgresSequences($pdo);
                }

            } catch (\PDOException $e) {
                $output->writeln("<error>Failed to import sample data: {$e->getMessage()}</error>");

                if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), 'does not exist')) {
                    $output->writeln("<error>Database '{$dbName}' does not exist. Please create it first.</error>");
                } elseif (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), 'authentication failed')) {
                    $output->writeln('<error>Access denied. Please check your database credentials.</error>');
                }

                return Command::FAILURE;
            }

            $this->clearEavAttributeCache($output);
            $this->importBlogPosts($sampleDataDir, $output);
            $output->writeln('<info>Sample data, media files, and database content installed successfully</info>');
            $output->writeln('<info>Please run ./maho index:reindex:all && ./maho cache:flush</info>');

            // Clean up
            unlink($tempFile);
            $rmCommand = 'rm -rf ' . escapeshellarg($targetDir . "/{$sampleDataDirName}");
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

    private function showLocalizationSuggestions(InputInterface $input, OutputInterface $output): void
    {
        $locale = $input->getOption('locale');

        if (!$locale || $locale === 'en_US' || $input->getOption('sample_data')) {
            return;
        }

        $availableLanguagePacks = [
            'de_DE', 'el_GR', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR', 'pt_PT',
        ];

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

        if (in_array($locale, $availableLanguagePacks, true)) {
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

        // Check if we're not in interactive mode or user has confirmed
        if ($input->isInteractive()) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new \Symfony\Component\Console\Question\ConfirmationQuestion(
                "\n<question>WARNING: This will clear all tables in the database and remove configuration. Continue? [y/N]</question> ",
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return false;
            }
        }

        // Remove local.xml if it exists (use hardcoded path since Mage isn't initialized yet)
        $localXmlPath = getcwd() . '/app/etc/local.xml';
        if (file_exists($localXmlPath)) {
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

    private function clearEavAttributeCache(OutputInterface $output): void
    {
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_singleton/eav/config');
        Mage::unregister('_helper/eav');
    }

    /**
     * Update PostgreSQL sequences to be higher than the max ID in each table
     * This is necessary after importing data with explicit IDs
     */
    private function updatePostgresSequences(\PDO $pdo): void
    {
        // Get all sequences in the database
        $stmt = $pdo->query("
            SELECT
                seq.relname as sequence_name,
                tab.relname as table_name,
                col.attname as column_name
            FROM pg_class seq
            JOIN pg_depend dep ON seq.oid = dep.objid
            JOIN pg_class tab ON dep.refobjid = tab.oid
            JOIN pg_attribute col ON col.attrelid = tab.oid AND col.attnum = dep.refobjsubid
            WHERE seq.relkind = 'S'
            AND dep.deptype = 'a'
            ORDER BY seq.relname
        ");

        $sequences = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($sequences as $seq) {
            $sequenceName = $seq['sequence_name'];
            $tableName = $seq['table_name'];
            $columnName = $seq['column_name'];

            try {
                // Get the max ID from the table
                $maxStmt = $pdo->query("SELECT COALESCE(MAX(\"{$columnName}\"), 0) as max_id FROM \"{$tableName}\"");
                $maxId = (int) $maxStmt->fetchColumn();

                if ($maxId > 0) {
                    // Set the sequence to max_id + 1
                    $pdo->exec("SELECT setval('\"{$sequenceName}\"', {$maxId}, true)");
                }
            } catch (\PDOException $e) {
                // Skip if table doesn't exist or other errors
                continue;
            }
        }
    }

    private function importBlogPosts(string $sampleDataDir, OutputInterface $output): void
    {
        if (!Mage::getConfig()->getModuleConfig('Maho_Blog') || !Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $output->writeln('<comment>Blog module not available, skipping blog import</comment>');
            return;
        }

        $csvPath = $sampleDataDir . '/blog_posts_en.csv';
        if (!file_exists($csvPath)) {
            $output->writeln('<comment>Blog CSV file not found, skipping blog import</comment>');
            return;
        }

        $output->writeln('<info>Importing blog posts from CSV...</info>');

        try {
            // Get the English store view ID
            $store = Mage::getModel('core/store')->load('en', 'code');
            $storeId = $store->getId();

            // Read and parse CSV
            $csvData = array_map('str_getcsv', file($csvPath));
            $headers = array_shift($csvData); // Remove header row

            $importedCount = 0;
            foreach ($csvData as $row) {
                $postData = array_combine($headers, $row);
                $post = Mage::getModel('blog/post');
                $post->setData([
                    'title' => $postData['title'],
                    'url_key' => $postData['url_key'],
                    'is_active' => (bool) $postData['is_active'],
                    'publish_date' => $postData['publish_date'],
                    'content' => $postData['content'],
                    'image' => $postData['image'],
                    'meta_title' => $postData['meta_title'],
                    'meta_description' => $postData['meta_description'],
                    'meta_keywords' => $postData['meta_keywords'],
                ]);
                $post->setStores([$storeId]);
                $post->save();
                $importedCount++;
            }

            $output->writeln("<info>Successfully imported {$importedCount} blog posts</info>");
        } catch (Exception $e) {
            $output->writeln("<error>Failed to import blog posts: {$e->getMessage()}</error>");
        }
    }

    /**
     * Execute SQL content for the specified database engine
     */
    private function executeSqlForEngine(\PDO $pdo, string $sql, string $dbEngine, OutputInterface $output): void
    {
        $converter = new \MahoCLI\Helper\SqlConverter();
        $converter->setPdo($pdo);

        if ($dbEngine === 'pgsql') {
            $convertedSql = $converter->mysqlToPostgresql($sql);
            $converter->executeStatements($pdo, $convertedSql, function ($current, $total) use ($output) {
                if ($current === $total || $current % 500 === 0) {
                    $output->write("\r<comment>  Progress: {$current}/{$total} statements...</comment>");
                }
            });
            $output->writeln('');
        } elseif ($dbEngine === 'sqlite') {
            $convertedSql = $converter->mysqlToSqlite($sql);
            $converter->executeStatements($pdo, $convertedSql, function ($current, $total) use ($output) {
                if ($current === $total || $current % 500 === 0) {
                    $output->write("\r<comment>  Progress: {$current}/{$total} statements...</comment>");
                }
            });
            $output->writeln('');
        } else {
            // MySQL - direct execution
            $pdo->exec($sql);
        }
    }
}
