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
use Mage;
use Mage_Catalog_Model_Category;
use Mage_Catalog_Model_Product;
use Mage_Cms_Model_Block;
use Mage_Core_Model_Locale;
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
        $this->addOption('sample_data_path', null, InputOption::VALUE_OPTIONAL, 'Path to local sample data directory (skips download)');

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

        $output->writeln('');

        // Download and decompress sample data
        $sampleDataPath = $input->getOption('sample_data_path');
        if ($input->getOption('sample_data') || $sampleDataPath) {
            $targetDir = Mage::getBaseDir();
            $cleanupDir = null; // Directory to clean up after import (only for downloaded data)
            $tempFile = null; // Temp file to clean up (only for downloaded data)

            if ($sampleDataPath) {
                // Use provided local path
                $sampleDataPath = rtrim($sampleDataPath, '/');
                if (!is_dir($sampleDataPath)) {
                    $output->writeln("<error>Sample data path does not exist: {$sampleDataPath}</error>");
                    return Command::FAILURE;
                }
                $output->writeln("<info>Using local sample data from: {$sampleDataPath}</info>");
                $sampleDataDir = $sampleDataPath;

                // Copy media files from local path
                $sourceMediaDir = $sampleDataDir . '/media';
                if (is_dir($sourceMediaDir)) {
                    $output->writeln('<info>Copying media files...</info>');
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
                }
            } else {
                // Download from GitHub
                $output->writeln('<info>Downloading sample data...</info>');

                // Get Maho version and determine the corresponding branch
                $mahoVersion = Mage::getVersion(); // e.g., "25.9.0"
                $versionParts = explode('.', $mahoVersion);
                $branchVersion = "{$versionParts[0]}.{$versionParts[1]}"; // e.g., "25.9"

                $sampleDataUrl = "https://github.com/MahoCommerce/maho-sample-data/archive/refs/heads/{$branchVersion}.tar.gz";
                $tempFile = tempnam(sys_get_temp_dir(), 'maho_sample_data');

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

                $sampleDataDir = $targetDir . "/{$sampleDataDirName}";
                $cleanupDir = $sampleDataDir;
            }

            $output->writeln('<info>Installing sample database</info>');

            $dbHost = $input->getOption('db_host');
            $dbName = $input->getOption('db_name');
            $dbUser = $input->getOption('db_user');
            $dbPass = $input->getOption('db_pass');
            $dbEngine = $input->getOption('db_engine') ?? 'mysql';

            // Detect format: JSON (new) or SQL (legacy)
            $useJsonFormat = file_exists($sampleDataDir . '/attributes.json');

            if ($useJsonFormat) {
                $output->writeln('<info>Detected JSON format sample data</info>');
                try {
                    $this->importSampleDataJson($sampleDataDir, $output);
                } catch (Exception $e) {
                    $output->writeln("<error>Failed to import sample data: {$e->getMessage()}</error>");
                    return Command::FAILURE;
                }
            } else {
                // Legacy SQL import
                $output->writeln('<info>Detected legacy SQL format sample data</info>');
                $sqlFiles = ['db_preparation.sql', 'db_data.sql'];

                try {
                    // Create PDO connection based on database engine
                    if ($dbEngine === 'pgsql') {
                        $dsn = "pgsql:host={$dbHost};dbname={$dbName}";
                        $pdo = new \PDO($dsn, $dbUser, $dbPass);
                        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                        // Use SQL converter for PostgreSQL
                        $converter = new \MahoCLI\Helper\SqlConverter();
                        $converter->setPdo($pdo);

                        // Disable foreign key checks for the import
                        // PostgreSQL uses session_replication_role to disable triggers/constraints
                        $pdo->exec('SET session_replication_role = replica');

                        foreach ($sqlFiles as $sqlFile) {
                            $sqlFilePath = $sampleDataDir . '/' . $sqlFile;
                            $output->writeln("<info>Importing {$sqlFile} (converting to PostgreSQL)...</info>");

                            // Read and convert MySQL SQL to PostgreSQL
                            $mysqlContent = file_get_contents($sqlFilePath);
                            $pgsqlContent = $converter->mysqlToPostgresql($mysqlContent);

                            // Execute statements one by one for better error handling
                            $converter->executeStatements($pdo, $pgsqlContent, function ($current, $total) use ($output) {
                                if ($current === $total || $current % 500 === 0) {
                                    $output->write("\r<comment>  Progress: {$current}/{$total} statements...</comment>");
                                }
                            });
                            $output->writeln("\n<info>Successfully imported {$sqlFile}</info>");
                        }

                        // Re-enable foreign key checks
                        $pdo->exec('SET session_replication_role = DEFAULT');

                        // Update sequences after importing data with explicit IDs
                        $output->writeln('<info>Updating PostgreSQL sequences...</info>');
                        $this->updatePostgresSequences($pdo);

                    } elseif ($dbEngine === 'sqlite') {
                        // SQLite - convert MySQL SQL to SQLite
                        $dbPath = $dbName;
                        // Resolve relative paths
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

                        // Use SQL converter for SQLite
                        $converter = new \MahoCLI\Helper\SqlConverter();
                        $converter->setPdo($pdo);

                        foreach ($sqlFiles as $sqlFile) {
                            $sqlFilePath = $sampleDataDir . '/' . $sqlFile;
                            $output->writeln("<info>Importing {$sqlFile} (converting to SQLite)...</info>");

                            // Read and convert MySQL SQL to SQLite
                            $mysqlContent = file_get_contents($sqlFilePath);
                            $sqliteContent = $converter->mysqlToSqlite($mysqlContent);

                            // Execute statements one by one for better error handling
                            $converter->executeStatements($pdo, $sqliteContent, function ($current, $total) use ($output) {
                                if ($current === $total || $current % 500 === 0) {
                                    $output->write("\r<comment>  Progress: {$current}/{$total} statements...</comment>");
                                }
                            });
                            $output->writeln("\n<info>Successfully imported {$sqlFile}</info>");
                        }

                    } else {
                        // MySQL - use direct execution
                        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8";
                        $pdo = new \PDO($dsn, $dbUser, $dbPass);
                        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                        foreach ($sqlFiles as $sqlFile) {
                            $sqlFilePath = $sampleDataDir . '/' . $sqlFile;
                            $output->writeln("<info>Importing {$sqlFile}...</info>");

                            // Read SQL file content
                            $sqlContent = file_get_contents($sqlFilePath);

                            // Execute the entire file as a single operation
                            $pdo->exec($sqlContent);
                            $output->writeln("<info>Successfully imported {$sqlFile}</info>");
                        }
                    }

                } catch (\PDOException $e) {
                    $output->writeln("<error>Failed to import sample data: {$e->getMessage()}</error>");

                    // If it's a connection error, provide more helpful message
                    if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), 'does not exist')) {
                        $output->writeln("<error>Database '{$dbName}' does not exist. Please create it first.</error>");
                    } elseif (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), 'authentication failed')) {
                        $output->writeln('<error>Access denied. Please check your database credentials.</error>');
                    }

                    return Command::FAILURE;
                }

                $this->importBlogPosts($sampleDataDir, $output);
            }

            $this->clearEavAttributeCache($output);
            $output->writeln('<info>Sample data, media files, and database content installed successfully</info>');
            $output->writeln('<info>Please run ./maho index:reindex:all && ./maho cache:flush</info>');

            // Clean up downloaded files (not when using local path)
            if ($cleanupDir !== null) {
                if ($tempFile !== null && file_exists($tempFile)) {
                    unlink($tempFile);
                }
                $rmCommand = 'rm -rf ' . escapeshellarg($cleanupDir);
                exec($rmCommand, $rmOutput, $rmReturnVar);

                if ($rmReturnVar !== 0) {
                    $output->writeln("<error>Failed to remove temporary files. rm command returned: $rmReturnVar</error>");
                    foreach ($rmOutput as $line) {
                        $output->writeln($line);
                    }
                    // We don't return FAILURE here as the installation itself was successful
                }
            }
        }

        return Command::SUCCESS;
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
     * Import sample data from JSON format
     */
    private function importSampleDataJson(string $sampleDataDir, OutputInterface $output): void
    {
        // 1. Import static data first (tax classes, customer groups, ratings)
        $this->importStaticDataFromJson($sampleDataDir, $output);

        // 2. Import permission_block
        if (file_exists($sampleDataDir . '/permission_block.json')) {
            $this->importPermissionBlockFromJson($sampleDataDir, $output);
        }

        // 3. Create custom attributes first (before attribute sets)
        if (file_exists($sampleDataDir . '/attributes.json')) {
            $this->createAttributesFromJson($sampleDataDir, $output);
            // Clear EAV cache so products can see the new attributes
            $this->clearEavAttributeCache($output);
        }

        // 4. Create attribute sets (assigns attributes including the newly created ones)
        if (file_exists($sampleDataDir . '/attribute_sets.json')) {
            $this->createAttributeSetsFromJson($sampleDataDir, $output);
        }

        // 5. Import config data (AFTER attributes so swatch attribute codes can be resolved to IDs)
        if (file_exists($sampleDataDir . '/config.json')) {
            $this->importConfigFromJson($sampleDataDir, $output);
        }

        // 6. Import CMS content (BEFORE categories so landing pages can be resolved)
        if (file_exists($sampleDataDir . '/cms.json')) {
            $this->importCmsFromJson($sampleDataDir, $output);
        }

        // 7. Import categories (with media)
        if (file_exists($sampleDataDir . '/categories.json')) {
            $this->copyCategoryMediaFiles($sampleDataDir, $output);
            $this->importCategoriesFromJson($sampleDataDir, $output);
        }

        // 8. Import products
        if (file_exists($sampleDataDir . '/products.json')) {
            $this->importProductsFromJson($sampleDataDir, $output);
        }

        // 9. Import blog posts
        if (file_exists($sampleDataDir . '/blog.json')) {
            $this->importBlogFromJson($sampleDataDir, $output);
        }

        // 10. Import reviews
        if (file_exists($sampleDataDir . '/reviews.json')) {
            $this->importReviewsFromJson($sampleDataDir, $output);
        }

        // 11. Import tax rules
        if (file_exists($sampleDataDir . '/tax_rules.json')) {
            $this->importTaxRulesFromJson($sampleDataDir, $output);
        }

        // 12. Import product links (related, upsell, cross-sell)
        if (file_exists($sampleDataDir . '/product_links.json')) {
            $this->importProductLinksFromJson($sampleDataDir, $output);
        }

        // 13. Import tier prices
        if (file_exists($sampleDataDir . '/tier_prices.json')) {
            $this->importTierPricesFromJson($sampleDataDir, $output);
        }

        // 13. Import custom options
        if (file_exists($sampleDataDir . '/custom_options.json')) {
            $this->importCustomOptionsFromJson($sampleDataDir, $output);
        }

        // 14. Import dynamic category rules
        if (file_exists($sampleDataDir . '/dynamic_category_rules.json')) {
            $this->importDynamicCategoryRulesFromJson($sampleDataDir, $output);
        }

        // 15. Reindex all
        $output->writeln('<info>Reindexing...</info>');
        $indexer = Mage::getSingleton('index/indexer');
        $processes = $indexer->getProcessesCollection();
        foreach ($processes as $process) {
            $process->reindexEverything();
        }
    }

    /**
     * Import static data from JSON files (tax classes, customer groups, ratings)
     */
    private function importStaticDataFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing static data...</info>');

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Import tax classes
        if (file_exists($sampleDataDir . '/tax_classes.json')) {
            $json = file_get_contents($sampleDataDir . '/tax_classes.json');
            $data = Mage::helper('core')->jsonDecode($json);
            $table = $connection->getTableName('tax_class');
            $count = 0;

            foreach ($data['tax_classes'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['class_id'])->where('class_id = ?', $row['class_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                    $count++;
                }
            }
            $output->writeln("  Imported tax_classes.json ({$count} entries)");
        }

        // Import tax rates
        if (file_exists($sampleDataDir . '/tax_rates.json')) {
            $json = file_get_contents($sampleDataDir . '/tax_rates.json');
            $data = Mage::helper('core')->jsonDecode($json);
            $table = $connection->getTableName('tax_calculation_rate');
            $count = 0;

            foreach ($data['tax_rates'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['tax_calculation_rate_id'])
                        ->where('tax_calculation_rate_id = ?', $row['tax_calculation_rate_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                    $count++;
                }
            }
            $output->writeln("  Imported tax_rates.json ({$count} entries)");
        }

        // Import customer groups
        if (file_exists($sampleDataDir . '/customer_groups.json')) {
            $json = file_get_contents($sampleDataDir . '/customer_groups.json');
            $data = Mage::helper('core')->jsonDecode($json);
            $table = $connection->getTableName('customer_group');
            $count = 0;

            foreach ($data['customer_groups'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['customer_group_id'])
                        ->where('customer_group_id = ?', $row['customer_group_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                    $count++;
                }
            }
            $output->writeln("  Imported customer_groups.json ({$count} entries)");
        }

        // Import ratings (rating, rating_option, rating_store)
        if (file_exists($sampleDataDir . '/ratings.json')) {
            $json = file_get_contents($sampleDataDir . '/ratings.json');
            $data = Mage::helper('core')->jsonDecode($json);

            // Import main ratings
            $table = $connection->getTableName('rating');
            $ratingCount = 0;
            foreach ($data['ratings'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['rating_id'])->where('rating_id = ?', $row['rating_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                    $ratingCount++;
                }
            }

            // Import rating options (1-5 scale)
            $table = $connection->getTableName('rating_option');
            $optionCount = 0;
            foreach ($data['rating_options'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['option_id'])->where('option_id = ?', $row['option_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                    $optionCount++;
                }
            }

            // Import rating store associations
            $table = $connection->getTableName('rating_store');
            foreach ($data['rating_stores'] ?? [] as $row) {
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, ['rating_id'])
                        ->where('rating_id = ?', $row['rating_id'])
                        ->where('store_id = ?', $row['store_id']),
                );
                if (!$exists) {
                    $connection->insert($table, $row);
                }
            }

            $output->writeln("  Imported ratings.json ({$ratingCount} ratings, {$optionCount} options)");
        }
    }

    /**
     * Import configuration data from JSON (core_config_data)
     */
    private function importConfigFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing configuration...</info>');

        $json = file_get_contents($sampleDataDir . '/config.json');
        $data = Mage::helper('core')->jsonDecode($json);

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $configCount = 0;

        // Import core_config_data
        if (!empty($data['core_config'])) {
            $configTable = $connection->getTableName('core_config_data');
            foreach ($data['core_config'] as $config) {
                $value = $config['value'];

                // Convert attribute codes back to IDs for configswatches settings
                if (str_starts_with($config['path'], 'configswatches/general/')) {
                    $value = $this->convertAttributeCodesToIdsInConfig($config['path'], $value);
                }

                // Check if config exists
                $select = $connection->select()
                    ->from($configTable, ['config_id'])
                    ->where('scope = ?', $config['scope'])
                    ->where('scope_id = ?', $config['scope_id'])
                    ->where('path = ?', $config['path']);

                $existingId = $connection->fetchOne($select);

                if ($existingId) {
                    // Update existing
                    $connection->update(
                        $configTable,
                        ['value' => $value],
                        ['config_id = ?' => $existingId],
                    );
                } else {
                    // Insert new
                    $connection->insert($configTable, [
                        'scope' => $config['scope'],
                        'scope_id' => $config['scope_id'],
                        'path' => $config['path'],
                        'value' => $value,
                    ]);
                    $configCount++;
                }
            }
        }

        $output->writeln("  Imported {$configCount} config entries");
    }

    /**
     * Import permission_block from JSON
     */
    private function importPermissionBlockFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing block permissions...</info>');

        $json = file_get_contents($sampleDataDir . '/permission_block.json');
        $data = Mage::helper('core')->jsonDecode($json);

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $blockCount = 0;

        if (!empty($data['permission_block'])) {
            $blockTable = $connection->getTableName('permission_block');
            foreach ($data['permission_block'] as $block) {
                // Check if permission exists
                $select = $connection->select()
                    ->from($blockTable, ['block_id'])
                    ->where('block_name = ?', $block['block_name']);

                $existingId = $connection->fetchOne($select);

                if (!$existingId) {
                    $connection->insert($blockTable, [
                        'block_name' => $block['block_name'],
                        'is_allowed' => $block['is_allowed'],
                    ]);
                    $blockCount++;
                }
            }
        }

        $output->writeln("  Imported {$blockCount} block permissions");
    }

    /**
     * Create attribute sets from JSON
     */
    private function createAttributeSetsFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Creating attribute sets...</info>');

        $json = file_get_contents($sampleDataDir . '/attribute_sets.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['attribute_sets'])) {
            $output->writeln('  No attribute sets to import');
            return;
        }

        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $defaultSetId = Mage::getModel('catalog/product')->getDefaultAttributeSetId();
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $entityAttrTable = $connection->getTableName('eav_entity_attribute');
        $setCount = 0;

        // Build attribute code to ID map
        $attributeIdMap = [];
        $attrRows = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('eav_attribute'), ['attribute_id', 'attribute_code'])
                ->where('entity_type_id = ?', $entityTypeId),
        );
        foreach ($attrRows as $row) {
            $attributeIdMap[$row['attribute_code']] = (int) $row['attribute_id'];
        }

        foreach ($data['attribute_sets'] as $setData) {
            // Check if attribute set already exists
            /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $existingSets */
            $existingSets = Mage::getModel('eav/entity_attribute_set')->getCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $setData['name']);

            if ($existingSets->getSize() > 0) {
                $attributeSet = $existingSets->getFirstItem();
            } else {
                // Create new attribute set based on Default
                /** @var \Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
                $attributeSet = Mage::getModel('eav/entity_attribute_set');
                $attributeSet->setEntityTypeId($entityTypeId);
                $attributeSet->setAttributeSetName($setData['name']);
                $attributeSet->save();
                $attributeSet->initFromSkeleton($defaultSetId);
                $attributeSet->save();
                $setCount++;
            }

            $attributeSetId = (int) $attributeSet->getId();

            // Process groups and attribute assignments
            foreach ($setData['groups'] ?? [] as $groupData) {
                // Find or create the group
                /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection $existingGroups */
                $existingGroups = Mage::getModel('eav/entity_attribute_group')->getCollection()
                    ->setAttributeSetFilter($attributeSetId)
                    ->addFieldToFilter('attribute_group_name', $groupData['name']);

                if ($existingGroups->getSize() > 0) {
                    $group = $existingGroups->getFirstItem();
                } else {
                    /** @var \Mage_Eav_Model_Entity_Attribute_Group $group */
                    $group = Mage::getModel('eav/entity_attribute_group');
                    $group->setAttributeSetId($attributeSetId);
                    $group->setAttributeGroupName($groupData['name']);
                    $group->setSortOrder($groupData['sort_order'] ?? 0);
                    $group->save();
                }

                $groupId = (int) $group->getId();

                // Assign attributes to this group using direct DB operations
                foreach ($groupData['attributes'] ?? [] as $attrData) {
                    $attributeId = $attributeIdMap[$attrData['code']] ?? null;
                    if (!$attributeId) {
                        continue;
                    }

                    // Check if assignment already exists
                    $exists = $connection->fetchOne(
                        $connection->select()
                            ->from($entityAttrTable, ['entity_attribute_id'])
                            ->where('entity_type_id = ?', $entityTypeId)
                            ->where('attribute_set_id = ?', $attributeSetId)
                            ->where('attribute_id = ?', $attributeId),
                    );

                    if ($exists) {
                        // Update existing assignment
                        $connection->update($entityAttrTable, [
                            'attribute_group_id' => $groupId,
                            'sort_order' => $attrData['sort_order'] ?? 0,
                        ], [
                            'entity_attribute_id = ?' => $exists,
                        ]);
                    } else {
                        // Create new assignment
                        $connection->insert($entityAttrTable, [
                            'entity_type_id' => $entityTypeId,
                            'attribute_set_id' => $attributeSetId,
                            'attribute_group_id' => $groupId,
                            'attribute_id' => $attributeId,
                            'sort_order' => $attrData['sort_order'] ?? 0,
                        ]);
                    }
                }
            }
        }

        $output->writeln("  Created {$setCount} attribute sets");
    }

    /**
     * Create custom product attributes from JSON
     */
    private function createAttributesFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Creating custom attributes...</info>');

        $json = file_get_contents($sampleDataDir . '/attributes.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['catalog_product'])) {
            $output->writeln('  No attributes to import');
            return;
        }

        // Scan products to find attributes used as super attributes for configurable products
        $superAttributes = [];
        $productsFile = $sampleDataDir . '/products.json';
        if (file_exists($productsFile)) {
            $productsJson = file_get_contents($productsFile);
            $productsData = Mage::helper('core')->jsonDecode($productsJson);
            foreach ($productsData['products'] ?? [] as $productData) {
                if (($productData['type'] ?? '') === 'configurable' && !empty($productData['configurable_attributes'])) {
                    foreach ($productData['configurable_attributes'] as $attr) {
                        $code = is_array($attr) ? ($attr['attribute_code'] ?? '') : $attr;
                        if ($code) {
                            $superAttributes[$code] = true;
                        }
                    }
                }
            }
        }

        /** @var \Mage_Catalog_Model_Resource_Setup $installer */
        $installer = new \Mage_Catalog_Model_Resource_Setup('core_setup');
        $entityTypeId = $installer->getEntityTypeId('catalog_product');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Build store code to ID map
        $storeIdMap = [];
        $storeRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('core_store'), ['store_id', 'code']),
        );
        foreach ($storeRows as $row) {
            $storeIdMap[$row['code']] = (int) $row['store_id'];
        }

        $attributeCount = 0;
        $storeLabelsToAdd = []; // Collect store labels for later processing

        foreach ($data['catalog_product'] as $attributeCode => $config) {
            // Check if attribute already exists
            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode($entityTypeId, $attributeCode);

            $isNewAttribute = !$attribute->getId();

            if (!$isNewAttribute) {
                // Update source model for existing select/multiselect attributes if missing
                $inputType = $config['input'] ?? 'text';
                if (in_array($inputType, ['select', 'multiselect']) && !$attribute->getSourceModel()) {
                    $attribute->setSourceModel('eav/entity_attribute_source_table');
                    $attribute->save();
                }

                // Update is_configurable for attributes used as super attributes
                if (isset($superAttributes[$attributeCode])) {
                    $connection->update(
                        $connection->getTableName('catalog_eav_attribute'),
                        ['is_configurable' => 1],
                        ['attribute_id = ?' => $attribute->getId()],
                    );
                }

                // Add options for existing select/multiselect attributes if they don't have options
                if (in_array($inputType, ['select', 'multiselect']) && !empty($config['option']['values'])) {
                    // Check if attribute has options
                    $existingOptions = $connection->fetchOne(
                        $connection->select()
                            ->from($connection->getTableName('eav_attribute_option'), 'COUNT(*)')
                            ->where('attribute_id = ?', $attribute->getId()),
                    );
                    if (!$existingOptions) {
                        $installer->addAttributeOption([
                            'attribute_id' => $attribute->getId(),
                            'values' => $config['option']['values'],
                        ]);
                    }
                }

                // Still process store labels for existing attributes
                if (!empty($config['store_labels'])) {
                    $storeLabelsToAdd[$attributeCode] = $config['store_labels'];
                }
                continue;
            }

            $inputType = $config['input'] ?? 'text';
            $attributeData = [
                'type' => $config['type'] ?? 'varchar',
                'input' => $inputType,
                'label' => $config['label'] ?? $attributeCode,
                'global' => $config['global'] ?? \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
                'visible' => $config['visible'] ?? true,
                'required' => $config['required'] ?? false,
                'user_defined' => true,
                'searchable' => $config['searchable'] ?? false,
                'filterable' => $config['filterable'] ?? false,
                'comparable' => $config['comparable'] ?? false,
                'visible_on_front' => $config['visible_on_front'] ?? false,
                'used_in_product_listing' => $config['used_in_product_listing'] ?? false,
                // Force is_configurable for attributes used as super attributes
                'is_configurable' => isset($superAttributes[$attributeCode]) || ($config['is_configurable'] ?? false),
            ];

            // Add source and backend models for select/multiselect attributes
            if (in_array($inputType, ['select', 'multiselect'])) {
                $attributeData['source'] = 'eav/entity_attribute_source_table';
                if ($inputType === 'multiselect') {
                    $attributeData['backend'] = 'eav/entity_attribute_backend_array';
                }
            }

            // Add options if present - using simpler 'values' format
            if (!empty($config['option']['values'])) {
                $attributeData['option'] = ['values' => $config['option']['values']];
            }

            $installer->addAttribute('catalog_product', $attributeCode, $attributeData);
            $attributeCount++;

            // For select/multiselect, ensure source model is set in DB
            if (in_array($inputType, ['select', 'multiselect'])) {
                $newAttribute = Mage::getModel('eav/entity_attribute')
                    ->loadByCode($entityTypeId, $attributeCode);
                if ($newAttribute->getId() && !$newAttribute->getSourceModel()) {
                    $connection->update(
                        $connection->getTableName('eav_attribute'),
                        ['source_model' => 'eav/entity_attribute_source_table'],
                        ['attribute_id = ?' => $newAttribute->getId()],
                    );
                }
            }

            // Store labels to add after attribute creation
            if (!empty($config['store_labels'])) {
                $storeLabelsToAdd[$attributeCode] = $config['store_labels'];
            }
        }

        // Add store-specific labels
        if (!empty($storeLabelsToAdd)) {
            $labelTable = $connection->getTableName('eav_attribute_label');
            foreach ($storeLabelsToAdd as $attributeCode => $storeLabels) {
                $attribute = Mage::getModel('eav/entity_attribute')
                    ->loadByCode($entityTypeId, $attributeCode);

                if (!$attribute->getId()) {
                    continue;
                }

                foreach ($storeLabels as $storeCode => $label) {
                    $storeId = $storeIdMap[$storeCode] ?? null;
                    if ($storeId === null) {
                        continue;
                    }

                    // Check if label already exists
                    $exists = $connection->fetchOne(
                        $connection->select()->from($labelTable, ['attribute_label_id'])
                            ->where('attribute_id = ?', $attribute->getId())
                            ->where('store_id = ?', $storeId),
                    );

                    if (!$exists) {
                        $connection->insert($labelTable, [
                            'attribute_id' => $attribute->getId(),
                            'store_id' => $storeId,
                            'value' => $label,
                        ]);
                    }
                }
            }
        }

        // Clear EAV cache so newly created attributes can be found
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_singleton/eav/config');

        // Populate swatch data for select attributes (text swatches)
        // This creates entries in eav_attribute_option_swatch so ConfigurableSwatches recognizes them
        $swatchTable = $connection->getTableName('eav_attribute_option_swatch');
        $optionTable = $connection->getTableName('eav_attribute_option');
        $optionValueTable = $connection->getTableName('eav_attribute_option_value');

        // Reload the JSON data fresh to ensure we have all attributes
        $jsonData = Mage::helper('core')->jsonDecode(file_get_contents($sampleDataDir . '/attributes.json'));
        $swatchCount = 0;

        // Get all select/multiselect attributes
        foreach ($jsonData['catalog_product'] ?? [] as $attributeCode => $config) {
            $inputType = $config['input'] ?? 'text';
            if (!in_array($inputType, ['select', 'multiselect'])) {
                continue;
            }

            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode($entityTypeId, $attributeCode);

            if (!$attribute->getId()) {
                continue;
            }

            // Get all options for this attribute with their labels
            $options = $connection->fetchAll(
                $connection->select()
                    ->from(['o' => $optionTable], ['option_id'])
                    ->join(
                        ['ov' => $optionValueTable],
                        'ov.option_id = o.option_id AND ov.store_id = 0',
                        ['value'],
                    )
                    ->where('o.attribute_id = ?', $attribute->getId()),
            );

            $attrSwatchCount = 0;
            foreach ($options as $option) {
                // Check if swatch already exists
                $existingSwatch = $connection->fetchOne(
                    $connection->select()
                        ->from($swatchTable, ['value_id'])
                        ->where('option_id = ?', $option['option_id']),
                );

                if (!$existingSwatch) {
                    // Insert text swatch with the option label as value
                    try {
                        $connection->insert($swatchTable, [
                            'option_id' => $option['option_id'],
                            'value' => $option['value'], // Use label for text swatches
                        ]);
                        $swatchCount++;
                        $attrSwatchCount++;
                    } catch (\Exception $e) {
                        // Silently skip duplicates
                    }
                }
            }
        }

        $output->writeln("  Created {$swatchCount} swatches");

        // Clear all caches and reset EAV config to ensure updated is_configurable values are used
        Mage::app()->getCacheInstance()->flush();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();
        // Force reload of EAV config
        Mage::unregister('_singleton/eav/config');
        // Also clear category collection cache
        Mage::unregister('_resource_singleton/catalog/category_collection');

        $output->writeln("  Created {$attributeCount} attributes");
    }

    /**
     * Import categories from JSON
     */
    private function importCategoriesFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing categories...</info>');

        $json = file_get_contents($sampleDataDir . '/categories.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['categories'])) {
            $output->writeln('  No categories to import');
            return;
        }

        // Get default store and root category
        $store = Mage::app()->getStore(0);
        $rootCategoryId = Mage::app()->getStore('default')->getRootCategoryId() ?: 2;

        $categoryCount = $this->importCategoryTree($data['categories'], $rootCategoryId, $store->getId());
        $output->writeln("  Imported {$categoryCount} categories");
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     */
    private function importCategoryTree(array $categories, int $parentId, int $storeId): int
    {
        $count = 0;

        foreach ($categories as $catData) {
            // Check if category with this url_key already exists under this parent
            /** @var \Mage_Catalog_Model_Resource_Category_Collection $existing */
            $existing = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('url_key', $catData['url_key'])
                ->addAttributeToFilter('parent_id', $parentId);

            // Resolve landing page CMS block identifier to ID
            $landingPageId = null;
            if (!empty($catData['landing_page'])) {
                /** @var Mage_Cms_Model_Block $block */
                $block = Mage::getModel('cms/block')->load($catData['landing_page'], 'identifier');
                if ($block->getId()) {
                    $landingPageId = $block->getId();
                }
            }

            if ($existing->getSize() > 0) {
                // Update existing category
                $category = Mage::getModel('catalog/category')->load($existing->getFirstItem()->getId());
                $category->setStoreId($storeId);
                $category->addData([
                    'is_active' => $catData['is_active'] ?? 1,
                    'is_anchor' => $catData['is_anchor'] ?? 1,
                    'include_in_menu' => $catData['include_in_menu'] ?? 1,
                    'description' => $catData['description'] ?? '',
                    'image' => $catData['image'] ?? null,
                    'display_mode' => $catData['display_mode'] ?? 'PRODUCTS',
                    'page_layout' => $catData['page_layout'] ?? null,
                    'landing_page' => $landingPageId,
                ]);
                $category->save();
            } else {
                // Create new category
                /** @var Mage_Catalog_Model_Category $category */
                $category = Mage::getModel('catalog/category');
                $category->setStoreId($storeId);

                $category->setData([
                    'name' => $catData['name'],
                    'url_key' => $catData['url_key'],
                    'is_active' => $catData['is_active'] ?? 1,
                    'is_anchor' => $catData['is_anchor'] ?? 1,
                    'include_in_menu' => $catData['include_in_menu'] ?? 1,
                    'description' => $catData['description'] ?? '',
                    'image' => $catData['image'] ?? null,
                    'display_mode' => $catData['display_mode'] ?? 'PRODUCTS',
                    'page_layout' => $catData['page_layout'] ?? null,
                    'landing_page' => $landingPageId,
                    'path' => Mage::getModel('catalog/category')->load($parentId)->getPath(),
                ]);

                $category->save();
                $count++;
            }

            // Import children
            if (!empty($catData['children'])) {
                $count += $this->importCategoryTree($catData['children'], (int) $category->getId(), $storeId);
            }
        }

        return $count;
    }

    /**
     * Import products from JSON using ImportExport array adapter
     */
    private function importProductsFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing products...</info>');

        $json = file_get_contents($sampleDataDir . '/products.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['products'])) {
            $output->writeln('  No products to import');
            return;
        }

        // Copy media files first
        $this->copyMediaFiles($sampleDataDir, $output);

        // Convert JSON to import format
        $importData = $this->convertProductsToImportFormat($data['products'], $sampleDataDir);

        if (empty($importData)) {
            $output->writeln('  No valid products to import');
            return;
        }

        // Use ImportExport with array adapter
        /** @var Mage_ImportExport_Model_Import $import */
        $import = Mage::getModel('importexport/import');
        $import->setData([
            'entity' => 'catalog_product',
            'behavior' => \Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
        ]);

        // Create array adapter with the data
        $source = \Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($importData);

        // Set the source on entity adapter and validate
        $entityAdapter = $import->getEntityAdapter();
        $entityAdapter->setSource($source);

        $validationResult = $entityAdapter->validateData();

        if ($entityAdapter->getErrorsCount() > 0) {
            $output->writeln('<comment>  Validation warnings:</comment>');
            foreach ($entityAdapter->getErrorMessages() as $error => $rows) {
                $output->writeln("    - {$error} (rows: " . implode(', ', array_slice($rows, 0, 5)) . ')');
            }
        }

        if ($validationResult) {
            // Import the data
            $entityAdapter->importData();
            $output->writeln("  Imported {$entityAdapter->getProcessedEntitiesCount()} products");

            // Assign all products to default website
            $this->assignProductsToWebsite($output);

            // Assign additional categories
            $this->assignAdditionalCategories($data['products'], $output);
        } else {
            $output->writeln('<error>  Product import validation failed</error>');
        }
    }

    /**
     * Assign all products to the default website
     */
    private function assignProductsToWebsite(OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $productWebsiteTable = $connection->getTableName('catalog_product_website');
        $productTable = $connection->getTableName('catalog_product_entity');

        // Get website ID from database (first non-admin website)
        $websiteId = (int) $connection->fetchOne(
            $connection->select()
                ->from($connection->getTableName('core_website'), 'website_id')
                ->where('website_id > 0')
                ->order('sort_order ASC')
                ->limit(1),
        );
        if (!$websiteId) {
            $websiteId = 1;
        }

        // Insert products that are not yet assigned to the website
        $sql = "INSERT IGNORE INTO {$productWebsiteTable} (product_id, website_id)
                SELECT entity_id, {$websiteId} FROM {$productTable}";
        $connection->query($sql);

        $count = $connection->fetchOne("SELECT COUNT(*) FROM {$productWebsiteTable}");
        $output->writeln("  Assigned {$count} products to website");
    }

    /**
     * Assign additional categories to products after import
     * @param array<int, array<string, mixed>> $products
     */
    private function assignAdditionalCategories(array $products, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $categoryProductTable = $connection->getTableName('catalog_category_product');

        // Build category path to ID map
        $categoryPathMap = $this->buildCategoryPathMap();

        // Build SKU to product ID map
        $skuMap = [];
        $result = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($result as $row) {
            $skuMap[$row['sku']] = (int) $row['entity_id'];
        }

        $assignmentCount = 0;
        foreach ($products as $productData) {
            $sku = $productData['sku'] ?? null;
            $categories = $productData['categories'] ?? [];

            if (!$sku || empty($categories) || count($categories) <= 1) {
                continue;
            }

            $productId = $skuMap[$sku] ?? null;
            if (!$productId) {
                continue;
            }

            // Skip the first category (already assigned during import)
            for ($i = 1; $i < count($categories); $i++) {
                $categoryPath = $categories[$i];
                $categoryId = $categoryPathMap[$categoryPath] ?? null;
                if (!$categoryId) {
                    continue;
                }

                // Check if assignment already exists
                $exists = $connection->fetchOne(
                    $connection->select()
                        ->from($categoryProductTable, 'COUNT(*)')
                        ->where('category_id = ?', $categoryId)
                        ->where('product_id = ?', $productId),
                );

                if (!$exists) {
                    $connection->insert($categoryProductTable, [
                        'category_id' => $categoryId,
                        'product_id' => $productId,
                        'position' => 0,
                    ]);
                    $assignmentCount++;
                }
            }
        }

        if ($assignmentCount > 0) {
            $output->writeln("  Assigned {$assignmentCount} additional category associations");
        }
    }

    /**
     * Copy category media files from sample data to media directory
     */
    private function copyCategoryMediaFiles(string $sampleDataDir, OutputInterface $output): void
    {
        $sourceDir = $sampleDataDir . '/media/catalog/category';
        $destDir = Mage::getBaseDir('media') . '/catalog/category';

        if (!is_dir($sourceDir)) {
            return;
        }

        $output->writeln('  Copying category media files...');

        // Ensure destination directory exists
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        $count = 0;
        $files = scandir($sourceDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcFile = $sourceDir . '/' . $file;
            $destFile = $destDir . '/' . $file;
            if (is_file($srcFile) && !file_exists($destFile)) {
                copy($srcFile, $destFile);
                $count++;
            }
        }

        if ($count > 0) {
            $output->writeln("  Copied {$count} category media files");
        }
    }

    /**
     * Copy media files from sample data to media directory
     */
    private function copyMediaFiles(string $sampleDataDir, OutputInterface $output): void
    {
        $sourceDir = $sampleDataDir . '/media/catalog/product';
        // Copy to media/import for ImportExport module to process
        $destDir = Mage::getBaseDir('media') . '/import';

        if (!is_dir($sourceDir)) {
            return;
        }

        $output->writeln('  Copying media files...');

        // Ensure destination directory exists
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        // Use recursive copy
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $count = 0;
        foreach ($iterator as $item) {
            $target = $destDir . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                if (!file_exists($target)) {
                    copy($item->getPathname(), $target);
                    $count++;
                }
            }
        }

        $output->writeln("  Copied {$count} media files");
    }

    /**
     * Convert JSON products to ImportExport format
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function convertProductsToImportFormat(array $products, string $sampleDataDir): array
    {
        // First pass: collect all possible columns
        $allColumns = [
            'sku', '_type', '_attribute_set', 'name', 'price', 'status', 'visibility',
            'tax_class_id', 'description', 'short_description', 'weight', 'url_key',
            '_product_websites', '_root_category', '_category', 'special_price', 'image', 'small_image',
            'thumbnail', '_media_image', 'qty', 'is_in_stock', '_super_attribute_code',
            '_super_products_sku', '_associated_sku',
        ];

        // Collect all attribute codes from products
        foreach ($products as $productData) {
            if (!empty($productData['attributes'])) {
                foreach (array_keys($productData['attributes']) as $attrCode) {
                    if (!in_array($attrCode, $allColumns)) {
                        $allColumns[] = $attrCode;
                    }
                }
            }
        }

        // Sort products: simple/virtual first, then configurable/grouped (which reference other products)
        // This ensures child products exist before parent products try to link to them
        $typeOrder = ['simple' => 1, 'virtual' => 2, 'configurable' => 3, 'grouped' => 4];
        usort($products, function ($a, $b) use ($typeOrder) {
            $aOrder = $typeOrder[$a['type'] ?? 'simple'] ?? 5;
            $bOrder = $typeOrder[$b['type'] ?? 'simple'] ?? 5;
            return $aOrder <=> $bOrder;
        });

        // Second pass: build rows with all columns
        $importData = [];

        foreach ($products as $productData) {
            // Start with empty row having all columns
            $row = array_fill_keys($allColumns, '');

            // Fill in the data
            $row['sku'] = $productData['sku'];
            $row['_type'] = $productData['type'];
            $row['_attribute_set'] = $productData['attribute_set'] ?? 'Default';
            $row['name'] = $productData['name'];
            $row['price'] = $productData['price'] ?? 0;
            $row['status'] = $productData['status'] ?? 1;
            $row['visibility'] = $productData['visibility'] ?? 4;
            $row['tax_class_id'] = $productData['tax_class_id'] ?? 0;
            $row['description'] = $productData['description'] ?? '';
            $row['short_description'] = $productData['short_description'] ?? '';
            $row['weight'] = $productData['weight'] ?? '';
            $row['url_key'] = $productData['url_key'] ?? '';
            // Skip _product_websites - let it be assigned during post-import
            $row['_product_websites'] = '';

            // Categories - need to add a row for each category
            $categories = $productData['categories'] ?? [];
            $row['_root_category'] = 'Default Category';
            $row['_category'] = !empty($categories) ? $categories[0] : '';

            // Custom attributes
            if (!empty($productData['attributes'])) {
                foreach ($productData['attributes'] as $attrCode => $value) {
                    $row[$attrCode] = $value;
                }
            }

            // Special price
            if (isset($productData['special_price'])) {
                $row['special_price'] = $productData['special_price'];
            }

            // Images - strip leading slash for ImportExport module
            if (!empty($productData['images'])) {
                if (!empty($productData['images']['image'])) {
                    $row['image'] = ltrim($productData['images']['image'], '/');
                    $row['small_image'] = ltrim($productData['images']['small_image'] ?? $productData['images']['image'], '/');
                    $row['thumbnail'] = ltrim($productData['images']['thumbnail'] ?? $productData['images']['image'], '/');
                }
                if (!empty($productData['images']['gallery'])) {
                    $gallery = array_map(fn ($img) => ltrim($img, '/'), $productData['images']['gallery']);
                    $row['_media_image'] = implode(',', $gallery);
                }
            }

            // Stock
            if (!empty($productData['stock'])) {
                $row['qty'] = $productData['stock']['qty'] ?? 0;
                $row['is_in_stock'] = $productData['stock']['is_in_stock'] ?? 1;
            }

            // For configurable products, add super attributes and child products as continuation rows
            if ($productData['type'] === 'configurable') {
                $superAttrCodes = [];
                $childSkus = [];

                // Collect super attributes
                if (!empty($productData['configurable_attributes'])) {
                    $superAttrCodes = array_map(function ($attr) {
                        return is_array($attr) ? ($attr['attribute_code'] ?? '') : $attr;
                    }, $productData['configurable_attributes']);
                    $superAttrCodes = array_values(array_filter($superAttrCodes));
                }

                // Collect child SKUs
                if (!empty($productData['associated_skus'])) {
                    $childSkus = $productData['associated_skus'];
                }

                // First row has main data + first super attribute + first child SKU
                if (!empty($superAttrCodes)) {
                    $row['_super_attribute_code'] = array_shift($superAttrCodes);
                }
                if (!empty($childSkus)) {
                    $row['_super_products_sku'] = array_shift($childSkus);
                }

                $importData[] = $row;

                // Add continuation rows for remaining super attributes and child SKUs
                // Continuation rows have EMPTY sku to indicate they belong to previous product
                // Must use full column template to ensure array_combine works in adapter
                $maxRows = max(count($superAttrCodes), count($childSkus));
                for ($i = 0; $i < $maxRows; $i++) {
                    $contRow = array_fill_keys($allColumns, '');  // Full template with empty values
                    $contRow['sku'] = '';  // Empty SKU = continuation row
                    if (isset($superAttrCodes[$i])) {
                        $contRow['_super_attribute_code'] = $superAttrCodes[$i];
                    }
                    if (isset($childSkus[$i])) {
                        $contRow['_super_products_sku'] = $childSkus[$i];
                    }
                    $importData[] = $contRow;
                }
            } elseif ($productData['type'] === 'grouped') {
                // For grouped products, first row has main data + first associated SKU
                $assocSkus = $productData['associated_skus'] ?? [];
                if (!empty($assocSkus)) {
                    $row['_associated_sku'] = array_shift($assocSkus);
                }

                $importData[] = $row;

                // Add continuation rows for remaining associated SKUs
                foreach ($assocSkus as $assocSku) {
                    $contRow = array_fill_keys($allColumns, '');  // Full template with empty values
                    $contRow['sku'] = '';  // Empty SKU = continuation row
                    $contRow['_associated_sku'] = $assocSku;
                    $importData[] = $contRow;
                }
            } else {
                // Simple, virtual, etc. - just add the main row
                $importData[] = $row;
            }
        }

        return $importData;
    }

    /**
     * @return array<string, int>
     */
    private function buildCategoryPathMap(): array
    {
        $map = [];

        /** @var \Mage_Catalog_Model_Resource_Category_Collection $categories */
        $categories = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name');

        foreach ($categories as $category) {
            $pathIds = explode('/', (string) $category->getPath());
            // Remove root categories (1 and 2)
            $pathIds = array_slice($pathIds, 2);

            if (empty($pathIds)) {
                continue;
            }

            $names = [];
            foreach ($pathIds as $id) {
                /** @var Mage_Catalog_Model_Category $cat */
                $cat = Mage::getModel('catalog/category')->load($id);
                $names[] = $cat->getName();
            }

            $path = implode('/', $names);
            $map[$path] = (int) $category->getId();
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $productData
     * @param array<string, int> $categoryMap
     */
    private function createProduct(array $productData, array $categoryMap, string $sampleDataDir): void
    {
        // Check if product already exists
        $existingProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $productData['sku']);
        if ($existingProduct && $existingProduct->getId()) {
            return; // Skip existing products
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        // Get attribute set ID
        $attributeSetId = $this->getAttributeSetId($productData['attribute_set'] ?? 'Default');

        // Get website IDs
        $websiteIds = [];
        foreach ($productData['websites'] ?? ['base'] as $websiteCode) {
            try {
                $website = Mage::app()->getWebsite($websiteCode);
                $websiteIds[] = $website->getId();
            } catch (Exception $e) {
                // Use default website (ID 1 is typically the main website)
                $defaultStore = Mage::app()->getDefaultStoreView();
                $websiteIds[] = $defaultStore ? $defaultStore->getWebsiteId() : 1;
            }
        }

        // Get category IDs
        $categoryIds = [];
        foreach ($productData['categories'] ?? [] as $categoryPath) {
            if (isset($categoryMap[$categoryPath])) {
                $categoryIds[] = $categoryMap[$categoryPath];
            }
        }

        // Generate url_key from name if not provided
        $urlKey = $productData['url_key'] ?? '';
        if (empty($urlKey)) {
            $urlKey = Mage::getModel('catalog/product_url')->formatUrlKey($productData['name']);
        }

        $product->setData([
            'sku' => $productData['sku'],
            'type_id' => $productData['type'],
            'attribute_set_id' => $attributeSetId,
            'name' => $productData['name'],
            'url_key' => $urlKey,
            'description' => $productData['description'] ?? '',
            'short_description' => $productData['short_description'] ?? '',
            'price' => $productData['price'] ?? 0,
            'weight' => $productData['weight'] ?? null,
            'status' => $productData['status'] ?? 1,
            'visibility' => $productData['visibility'] ?? 4,
            'tax_class_id' => $productData['tax_class_id'] ?? 0,
            'website_ids' => $websiteIds,
            'category_ids' => $categoryIds,
        ]);

        if (isset($productData['special_price'])) {
            $product->setSpecialPrice($productData['special_price']);
        }

        // Set custom attributes
        if (!empty($productData['attributes'])) {
            foreach ($productData['attributes'] as $attrCode => $value) {
                $this->setProductAttribute($product, $attrCode, $value);
            }
        }

        // Handle stock
        if (!empty($productData['stock'])) {
            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'qty' => $productData['stock']['qty'] ?? 0,
                'is_in_stock' => $productData['stock']['is_in_stock'] ?? 1,
            ]);
        }

        // Set downloadable data BEFORE first save to avoid double-save issues
        if ($productData['type'] === 'downloadable' && !empty($productData['links'])) {
            $linksData = [];
            foreach ($productData['links'] as $linkData) {
                $linksData[] = [
                    'title' => $linkData['title'],
                    'price' => $linkData['price'] ?? 0,
                    'number_of_downloads' => $linkData['downloads'] ?? 0,
                    'is_shareable' => 2, // Use config
                    'link_type' => 'file',
                    'link_file' => $linkData['file'],
                ];
            }
            $product->setDownloadableData(['link' => $linksData]);
            $product->setLinksExist(true);
        }

        $product->save();

        // Handle images after save
        if (!empty($productData['images'])) {
            $this->addProductImages($product, $productData['images'], $sampleDataDir);
        }

        // Handle product type specific data (excluding downloadable which is handled above)
        switch ($productData['type']) {
            case 'configurable':
                $this->setupConfigurableProduct($product, $productData);
                break;
            case 'bundle':
                $this->setupBundleProduct($product, $productData);
                break;
            case 'grouped':
                $this->setupGroupedProduct($product, $productData);
                break;
        }
    }

    private function getAttributeSetId(string $setName): int
    {
        /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $collection */
        $collection = Mage::getModel('eav/entity_attribute_set')->getCollection()
            ->setEntityTypeFilter(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
            ->addFieldToFilter('attribute_set_name', $setName);

        if ($collection->getSize() > 0) {
            return (int) $collection->getFirstItem()->getId();
        }

        // Return default attribute set
        return (int) Mage::getModel('catalog/product')->getDefaultAttributeSetId();
    }

    private function setProductAttribute(Mage_Catalog_Model_Product $product, string $attrCode, mixed $value): void
    {
        $attribute = Mage::getModel('eav/entity_attribute')
            ->loadByCode('catalog_product', $attrCode);

        if (!$attribute->getId()) {
            return;
        }

        // For select/multiselect attributes, find option ID by label
        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
            $source = $attribute->getSource();
            foreach ($source->getAllOptions() as $option) {
                if (($option['label'] ?? '') === $value) {
                    $product->setData($attrCode, $option['value']);
                    return;
                }
            }
        }

        $product->setData($attrCode, $value);
    }

    /**
     * @param array<string, mixed> $images
     */
    private function addProductImages(Mage_Catalog_Model_Product $product, array $images, string $sampleDataDir): void
    {
        $productId = $product->getId();
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getSingleton('core/resource');

        $mediaDir = $sampleDataDir . '/media/catalog/product';
        $destMediaDir = Mage::getBaseDir('media') . '/catalog/product';

        // Get media gallery attribute ID
        $eavAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'media_gallery');
        $galleryAttributeId = $eavAttribute->getAttributeId();

        $galleryTable = $resource->getTableName('catalog/product_attribute_media_gallery');
        $galleryValueTable = $resource->getTableName('catalog/product_attribute_media_gallery_value');
        $varcharTable = $resource->getTableName('catalog_product_entity_varchar');

        $addedFiles = [];
        $position = 0;

        // Build map of file -> array of image types
        $allImages = [];
        foreach (['image', 'small_image', 'thumbnail'] as $imageType) {
            if (!empty($images[$imageType])) {
                $file = $images[$imageType];
                if (!isset($allImages[$file])) {
                    $allImages[$file] = [];
                }
                $allImages[$file][] = $imageType;
            }
        }
        if (!empty($images['gallery'])) {
            foreach ($images['gallery'] as $galleryImage) {
                if (!isset($allImages[$galleryImage])) {
                    $allImages[$galleryImage] = []; // Gallery only, no attributes
                }
            }
        }

        foreach ($allImages as $imageFile => $imageTypes) {
            $sourcePath = $mediaDir . $imageFile;
            if (!file_exists($sourcePath)) {
                continue;
            }

            // Copy file to destination if not already there
            $destPath = $destMediaDir . $imageFile;
            if (!file_exists($destPath)) {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                copy($sourcePath, $destPath);
            }

            // Insert into media gallery table if not already added
            if (!isset($addedFiles[$imageFile])) {
                $position++;
                $connection->insert($galleryTable, [
                    'attribute_id' => $galleryAttributeId,
                    'entity_id' => $productId,
                    'value' => $imageFile,
                ]);
                $valueId = $connection->lastInsertId();

                // Insert gallery value (store-level data)
                $connection->insert($galleryValueTable, [
                    'value_id' => $valueId,
                    'store_id' => 0,
                    'label' => null,
                    'position' => $position,
                    'disabled' => 0,
                ]);

                $addedFiles[$imageFile] = true;
            }

            // Set image/small_image/thumbnail attribute values for all types
            foreach ($imageTypes as $imageType) {
                $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $imageType);
                if ($attribute && $attribute->getId()) {
                    // Delete existing value
                    $connection->delete($varcharTable, [
                        'entity_id = ?' => $productId,
                        'attribute_id = ?' => $attribute->getId(),
                        'store_id = ?' => 0,
                    ]);

                    // Insert new value
                    $connection->insert($varcharTable, [
                        'entity_id' => $productId,
                        'attribute_id' => $attribute->getId(),
                        'store_id' => 0,
                        'value' => $imageFile,
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupConfigurableProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['configurable_attributes']) || empty($productData['associated_skus'])) {
            return;
        }

        $productId = $product->getId();
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Get configurable attribute IDs and pricing data
        $attributes = [];
        $pricingByAttrCode = [];

        foreach ($productData['configurable_attributes'] as $attrData) {
            $attrCode = is_array($attrData) ? ($attrData['attribute_code'] ?? '') : $attrData;
            if (empty($attrCode)) {
                continue;
            }

            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode('catalog_product', $attrCode);
            if ($attribute->getId()) {
                $attributes[] = $attribute;

                if (is_array($attrData) && !empty($attrData['pricing'])) {
                    $pricingByAttrCode[$attrCode] = $attrData['pricing'];
                }
            }
        }

        if (empty($attributes)) {
            return;
        }

        // Get associated product IDs
        $associatedProductIds = [];
        foreach ($productData['associated_skus'] as $sku) {
            $simpleProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
            if ($simpleProduct && $simpleProduct->getId()) {
                $associatedProductIds[] = $simpleProduct->getId();
            }
        }

        if (empty($associatedProductIds)) {
            return;
        }

        $superAttrTable = $connection->getTableName('catalog_product_super_attribute');
        $superAttrLabelTable = $connection->getTableName('catalog_product_super_attribute_label');
        $superAttrPricingTable = $connection->getTableName('catalog_product_super_attribute_pricing');
        $superLinkTable = $connection->getTableName('catalog_product_super_link');

        // Insert super attributes
        $position = 0;
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();

            // Insert super attribute
            $connection->insert($superAttrTable, [
                'product_id' => $productId,
                'attribute_id' => $attribute->getId(),
                'position' => $position++,
            ]);

            $superAttrId = $connection->lastInsertId($superAttrTable);

            // Insert label
            $connection->insert($superAttrLabelTable, [
                'product_super_attribute_id' => $superAttrId,
                'store_id' => 0,
                'use_default' => 0,
                'value' => $attribute->getFrontendLabel(),
            ]);

            // Insert pricing if available
            if (isset($pricingByAttrCode[$attrCode])) {
                foreach ($pricingByAttrCode[$attrCode] as $pricing) {
                    // Get option ID from label
                    $optionId = $this->getAttributeOptionIdByLabel($attribute, $pricing['value_label']);
                    if ($optionId) {
                        $connection->insert($superAttrPricingTable, [
                            'product_super_attribute_id' => $superAttrId,
                            'value_index' => $optionId,
                            'is_percent' => $pricing['is_percent'] ? 1 : 0,
                            'pricing_value' => $pricing['pricing_value'],
                            'website_id' => 0,
                        ]);
                    }
                }
            }
        }

        // Link child products
        foreach ($associatedProductIds as $childId) {
            $connection->insert($superLinkTable, [
                'parent_id' => $productId,
                'product_id' => $childId,
            ]);
        }
    }

    private function getAttributeOptionIdByLabel(\Mage_Eav_Model_Entity_Attribute $attribute, string $label): ?int
    {
        $options = $attribute->getSource()->getAllOptions(false);
        foreach ($options as $option) {
            if ($option['label'] === $label) {
                return (int) $option['value'];
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupBundleProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['bundle_options'])) {
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $productId = $product->getId();

        // Update price type via EAV
        $priceType = ($productData['price_type'] === 'fixed') ? 1 : 0;
        $priceTypeAttr = Mage::getModel('eav/entity_attribute')
            ->loadByCode('catalog_product', 'price_type');
        if ($priceTypeAttr->getId()) {
            $tableName = $connection->getTableName('catalog_product_entity_int');
            $connection->insertOnDuplicate(
                $tableName,
                [
                    'entity_id' => $productId,
                    'attribute_id' => $priceTypeAttr->getId(),
                    'store_id' => 0,
                    'value' => $priceType,
                ],
                ['value'],
            );
        }

        $bundleOptionTable = $connection->getTableName('catalog_product_bundle_option');
        $bundleOptionValueTable = $connection->getTableName('catalog_product_bundle_option_value');
        $bundleSelectionTable = $connection->getTableName('catalog_product_bundle_selection');

        foreach ($productData['bundle_options'] as $optionIndex => $optionData) {
            // Insert bundle option
            $connection->insert($bundleOptionTable, [
                'parent_id' => $productId,
                'required' => $optionData['required'] ? 1 : 0,
                'position' => $optionIndex,
                'type' => $optionData['type'],
            ]);

            $optionId = $connection->lastInsertId($bundleOptionTable);

            // Insert option title
            $connection->insert($bundleOptionValueTable, [
                'option_id' => $optionId,
                'store_id' => 0,
                'title' => $optionData['title'],
            ]);

            // Insert selections
            foreach ($optionData['products'] as $selectionIndex => $selectionData) {
                $simpleProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $selectionData['sku']);
                if ($simpleProduct && $simpleProduct->getId()) {
                    $connection->insert($bundleSelectionTable, [
                        'option_id' => $optionId,
                        'parent_product_id' => $productId,
                        'product_id' => $simpleProduct->getId(),
                        'position' => $selectionIndex,
                        'is_default' => $selectionData['is_default'] ?? 0,
                        'selection_price_type' => 0,
                        'selection_price_value' => 0,
                        'selection_qty' => $selectionData['qty'],
                        'selection_can_change_qty' => 1,
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupGroupedProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['associated_products'])) {
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $linkTable = $connection->getTableName('catalog_product_link');
        $linkAttributeTable = $connection->getTableName('catalog_product_link_attribute');
        $linkAttributeIntTable = $connection->getTableName('catalog_product_link_attribute_int');
        $linkAttributeDecimalTable = $connection->getTableName('catalog_product_link_attribute_decimal');

        $linkTypeId = \Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;

        // Get attribute IDs for position and qty
        $positionAttrId = $connection->fetchOne(
            $connection->select()
                ->from($linkAttributeTable, 'product_link_attribute_id')
                ->where('link_type_id = ?', $linkTypeId)
                ->where('product_link_attribute_code = ?', 'position'),
        );

        $qtyAttrId = $connection->fetchOne(
            $connection->select()
                ->from($linkAttributeTable, 'product_link_attribute_id')
                ->where('link_type_id = ?', $linkTypeId)
                ->where('product_link_attribute_code = ?', 'qty'),
        );

        $position = 0;
        foreach ($productData['associated_products'] as $assocData) {
            $simpleProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $assocData['sku']);
            if ($simpleProduct && $simpleProduct->getId()) {
                // Insert link
                $connection->insert($linkTable, [
                    'product_id' => $product->getId(),
                    'linked_product_id' => $simpleProduct->getId(),
                    'link_type_id' => $linkTypeId,
                ]);

                $linkId = $connection->lastInsertId($linkTable);

                // Insert position attribute
                if ($positionAttrId) {
                    $connection->insert($linkAttributeIntTable, [
                        'product_link_attribute_id' => $positionAttrId,
                        'link_id' => $linkId,
                        'value' => $assocData['position'] ?? $position++,
                    ]);
                }

                // Insert qty attribute if available
                if ($qtyAttrId && isset($assocData['qty'])) {
                    $connection->insert($linkAttributeDecimalTable, [
                        'product_link_attribute_id' => $qtyAttrId,
                        'link_id' => $linkId,
                        'value' => $assocData['qty'],
                    ]);
                }
            }
        }
    }

    /**
     * Import CMS pages and blocks from JSON
     */
    private function importCmsFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing CMS content...</info>');

        $json = file_get_contents($sampleDataDir . '/cms.json');
        $data = Mage::helper('core')->jsonDecode($json);

        $pageCount = 0;
        $blockCount = 0;

        // Import pages
        if (!empty($data['pages'])) {
            foreach ($data['pages'] as $pageData) {
                // Check if page exists
                /** @var \Mage_Cms_Model_Page $existingPage */
                $existingPage = Mage::getModel('cms/page')->load($pageData['identifier'], 'identifier');

                if ($existingPage->getId()) {
                    // Update existing page
                    $existingPage->addData([
                        'title' => $pageData['title'],
                        'content_heading' => $pageData['content_heading'] ?? '',
                        'content' => $pageData['content'],
                        'root_template' => $pageData['root_template'],
                        'is_active' => $pageData['is_active'],
                    ]);
                    $existingPage->setStores($pageData['stores'] ?? [0]);
                    $existingPage->save();
                } else {
                    // Create new page
                    /** @var \Mage_Cms_Model_Page $page */
                    $page = Mage::getModel('cms/page');
                    $page->setData([
                        'identifier' => $pageData['identifier'],
                        'title' => $pageData['title'],
                        'content_heading' => $pageData['content_heading'] ?? '',
                        'content' => $pageData['content'],
                        'root_template' => $pageData['root_template'],
                        'is_active' => $pageData['is_active'],
                    ]);
                    $page->setStores($pageData['stores'] ?? [0]);
                    $page->save();
                    $pageCount++;
                }
            }
        }

        // Import blocks
        if (!empty($data['blocks'])) {
            foreach ($data['blocks'] as $blockData) {
                // Check if block exists
                /** @var \Mage_Cms_Model_Block $existingBlock */
                $existingBlock = Mage::getModel('cms/block')->load($blockData['identifier'], 'identifier');

                if ($existingBlock->getId()) {
                    // Update existing block
                    $existingBlock->addData([
                        'title' => $blockData['title'],
                        'content' => $blockData['content'],
                        'is_active' => $blockData['is_active'],
                    ]);
                    $existingBlock->setStores($blockData['stores'] ?? [0]);
                    $existingBlock->save();
                } else {
                    // Create new block
                    /** @var \Mage_Cms_Model_Block $block */
                    $block = Mage::getModel('cms/block');
                    $block->setData([
                        'identifier' => $blockData['identifier'],
                        'title' => $blockData['title'],
                        'content' => $blockData['content'],
                        'is_active' => $blockData['is_active'],
                    ]);
                    $block->setStores($blockData['stores'] ?? [0]);
                    $block->save();
                    $blockCount++;
                }
            }
        }

        $output->writeln("  Imported {$pageCount} pages, {$blockCount} blocks");
    }

    /**
     * Import blog posts from JSON
     */
    private function importBlogFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $output->writeln('  Skipped: blog.json (module not enabled)');
            return;
        }

        $output->writeln('<info>Importing blog posts...</info>');

        $json = file_get_contents($sampleDataDir . '/blog.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['posts'])) {
            $output->writeln('  No blog posts to import');
            return;
        }

        $postCount = 0;
        foreach ($data['posts'] as $postData) {
            // Check if post with this url_key exists
            /** @var \Mage_Core_Model_Resource_Db_Collection_Abstract $existing */
            $existing = Mage::getModel('blog/post')->getCollection()
                ->addFieldToFilter('url_key', $postData['url_key']);

            if ($existing->getSize() > 0) {
                continue; // Skip existing
            }

            $post = Mage::getModel('blog/post');
            $post->setData([
                'url_key' => $postData['url_key'],
                'title' => $postData['title'],
                'content' => $postData['content'],
                'image' => $postData['image'] ?? null,
                'is_active' => $postData['is_active'] ?? 1,
                'publish_date' => $postData['publish_date'] ?? null,
                'meta_title' => $postData['meta_title'] ?? '',
                'meta_description' => $postData['meta_description'] ?? '',
                'meta_keywords' => $postData['meta_keywords'] ?? '',
            ]);
            $post->setStores($postData['store_ids'] ?? [0]);
            $post->save();
            $postCount++;
        }

        $output->writeln("  Imported {$postCount} blog posts");
    }

    /**
     * Import product reviews from JSON
     */
    private function importReviewsFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing reviews...</info>');

        $json = file_get_contents($sampleDataDir . '/reviews.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['reviews'])) {
            $output->writeln('  No reviews to import');
            return;
        }

        $reviewCount = 0;
        foreach ($data['reviews'] as $reviewData) {
            // Find product by SKU
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $reviewData['product_sku']);
            if (!$product || !$product->getId()) {
                continue;
            }

            /** @var \Mage_Review_Model_Review $review */
            $review = Mage::getModel('review/review');
            $review->setData([
                'entity_id' => $review->getEntityIdByCode(\Mage_Review_Model_Review::ENTITY_PRODUCT_CODE),
                'entity_pk_value' => $product->getId(),
                'status_id' => \Mage_Review_Model_Review::STATUS_APPROVED,
                'title' => $reviewData['title'],
                'detail' => $reviewData['detail'],
                'nickname' => $reviewData['nickname'],
                'store_id' => Mage::app()->getStore()->getId(),
            ]);

            $review->setStores([Mage::app()->getStore()->getId()]);
            $review->save();

            // Add ratings
            if (!empty($reviewData['ratings'])) {
                foreach ($reviewData['ratings'] as $ratingCode => $value) {
                    /** @var \Mage_Rating_Model_Rating $rating */
                    $rating = Mage::getModel('rating/rating')->getCollection()
                        ->addFieldToFilter('rating_code', $ratingCode)
                        ->getFirstItem();

                    if ($rating->getId()) {
                        // Get option ID for value (1-5 scale)
                        $options = $rating->getOptions();
                        $optionId = null;
                        foreach ($options as $option) {
                            if ($option->getValue() == $value) {
                                $optionId = $option->getId();
                                break;
                            }
                        }

                        if ($optionId) {
                            $rating->setRatingId($rating->getId())
                                ->setReviewId($review->getId())
                                ->addOptionVote($optionId, $product->getId());
                        }
                    }
                }
            }

            $review->aggregate();
            $reviewCount++;
        }

        $output->writeln("  Imported {$reviewCount} reviews");
    }

    /**
     * Convert attribute codes to IDs in configswatches settings
     */
    private function convertAttributeCodesToIdsInConfig(string $path, string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // These paths contain attribute codes that should be converted to IDs
        $codePaths = [
            'configswatches/general/product_list_attribute',
            'configswatches/general/swatch_attributes',
        ];

        if (!in_array($path, $codePaths)) {
            return $value;
        }

        // Split by comma for multiple codes
        $codes = array_map('trim', explode(',', $value));
        $ids = [];

        foreach ($codes as $code) {
            if (is_numeric($code)) {
                $ids[] = $code; // Already an ID
                continue;
            }

            /** @var \Mage_Eav_Model_Entity_Attribute $attribute */
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $code);
            if ($attribute->getId()) {
                $ids[] = $attribute->getId();
            }
        }

        return implode(',', $ids);
    }

    /**
     * Import tax rules and calculations from JSON
     */
    private function importTaxRulesFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing tax rules...</info>');

        $json = file_get_contents($sampleDataDir . '/tax_rules.json');
        $data = Mage::helper('core')->jsonDecode($json);

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Build lookup maps
        $taxClassIdMap = [];
        $taxClassRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_class'), ['class_id', 'class_name']),
        );
        foreach ($taxClassRows as $row) {
            $taxClassIdMap[$row['class_name']] = (int) $row['class_id'];
        }

        $taxRateIdMap = [];
        $taxRateRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation_rate'), ['tax_calculation_rate_id', 'code']),
        );
        foreach ($taxRateRows as $row) {
            $taxRateIdMap[$row['code']] = (int) $row['tax_calculation_rate_id'];
        }

        // Import tax rules
        $ruleTable = $connection->getTableName('tax_calculation_rule');
        $ruleCount = 0;
        $taxRuleIdMap = [];

        foreach ($data['tax_rules'] ?? [] as $row) {
            $exists = $connection->fetchOne(
                $connection->select()->from($ruleTable, ['tax_calculation_rule_id'])
                    ->where('code = ?', $row['code']),
            );
            if (!$exists) {
                $connection->insert($ruleTable, [
                    'code' => $row['code'],
                    'priority' => $row['priority'],
                    'position' => $row['position'],
                    'calculate_subtotal' => $row['calculate_subtotal'],
                ]);
                $taxRuleIdMap[$row['code']] = (int) $connection->lastInsertId();
                $ruleCount++;
            } else {
                $taxRuleIdMap[$row['code']] = (int) $exists;
            }
        }

        // Import tax calculations
        $calcTable = $connection->getTableName('tax_calculation');
        $calcCount = 0;

        foreach ($data['tax_calculations'] ?? [] as $row) {
            $rateId = $taxRateIdMap[$row['tax_rate_code']] ?? null;
            $ruleId = $taxRuleIdMap[$row['tax_rule_code']] ?? null;
            $customerClassId = $taxClassIdMap[$row['customer_tax_class_name']] ?? null;
            $productClassId = $taxClassIdMap[$row['product_tax_class_name']] ?? null;

            if (!$rateId || !$ruleId || !$customerClassId || !$productClassId) {
                continue;
            }

            // Check if calculation exists
            $exists = $connection->fetchOne(
                $connection->select()->from($calcTable, ['tax_calculation_id'])
                    ->where('tax_calculation_rate_id = ?', $rateId)
                    ->where('tax_calculation_rule_id = ?', $ruleId)
                    ->where('customer_tax_class_id = ?', $customerClassId)
                    ->where('product_tax_class_id = ?', $productClassId),
            );

            if (!$exists) {
                $connection->insert($calcTable, [
                    'tax_calculation_rate_id' => $rateId,
                    'tax_calculation_rule_id' => $ruleId,
                    'customer_tax_class_id' => $customerClassId,
                    'product_tax_class_id' => $productClassId,
                ]);
                $calcCount++;
            }
        }

        $output->writeln("  Imported {$ruleCount} tax rules, {$calcCount} tax calculations");
    }

    /**
     * Import product links (related, upsell, cross-sell) from JSON
     */
    private function importProductLinksFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing product links...</info>');

        $json = file_get_contents($sampleDataDir . '/product_links.json');
        $data = Mage::helper('core')->jsonDecode($json);

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Build SKU to product ID map
        $productIdMap = [];
        $productRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($productRows as $row) {
            $productIdMap[$row['sku']] = (int) $row['entity_id'];
        }

        $linkTable = $connection->getTableName('catalog_product_link');
        $linkAttrIntTable = $connection->getTableName('catalog_product_link_attribute_int');

        $linkTypes = [
            'related' => \Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED,
            'upsell' => \Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
            'crosssell' => \Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
        ];

        // Get position attribute IDs for each link type
        $positionAttrIds = [];
        $attrRows = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('catalog_product_link_attribute'), ['link_type_id', 'product_link_attribute_id'])
                ->where('product_link_attribute_code = ?', 'position'),
        );
        foreach ($attrRows as $row) {
            $positionAttrIds[(int) $row['link_type_id']] = (int) $row['product_link_attribute_id'];
        }

        $counts = ['related' => 0, 'upsell' => 0, 'crosssell' => 0];

        foreach ($linkTypes as $linkKey => $linkTypeId) {
            foreach ($data[$linkKey] ?? [] as $link) {
                $productId = $productIdMap[$link['product_sku']] ?? null;
                $linkedProductId = $productIdMap[$link['linked_sku']] ?? null;

                if (!$productId || !$linkedProductId) {
                    continue;
                }

                // Check if link exists
                $existingLinkId = $connection->fetchOne(
                    $connection->select()->from($linkTable, ['link_id'])
                        ->where('product_id = ?', $productId)
                        ->where('linked_product_id = ?', $linkedProductId)
                        ->where('link_type_id = ?', $linkTypeId),
                );

                if (!$existingLinkId) {
                    $connection->insert($linkTable, [
                        'product_id' => $productId,
                        'linked_product_id' => $linkedProductId,
                        'link_type_id' => $linkTypeId,
                    ]);
                    $linkId = (int) $connection->lastInsertId();
                    $counts[$linkKey]++;

                    // Add position attribute if available
                    if (isset($link['position']) && isset($positionAttrIds[$linkTypeId])) {
                        $connection->insert($linkAttrIntTable, [
                            'product_link_attribute_id' => $positionAttrIds[$linkTypeId],
                            'link_id' => $linkId,
                            'value' => $link['position'],
                        ]);
                    }
                }
            }
        }

        $total = $counts['related'] + $counts['upsell'] + $counts['crosssell'];
        $output->writeln("  Imported {$total} product links (" .
            "{$counts['related']} related, {$counts['upsell']} upsell, {$counts['crosssell']} crosssell)");
    }

    /**
     * Import tier prices from JSON
     */
    private function importTierPricesFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing tier prices...</info>');

        $json = file_get_contents($sampleDataDir . '/tier_prices.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['tier_prices'])) {
            $output->writeln('  No tier prices to import');
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Build SKU to product ID map
        $productIdMap = [];
        $productRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($productRows as $row) {
            $productIdMap[$row['sku']] = (int) $row['entity_id'];
        }

        // Build customer group code to ID map
        $customerGroupIdMap = [];
        $customerGroupRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('customer_group'), ['customer_group_id', 'customer_group_code']),
        );
        foreach ($customerGroupRows as $row) {
            $customerGroupIdMap[$row['customer_group_code']] = (int) $row['customer_group_id'];
        }

        $tierPriceTable = $connection->getTableName('catalog_product_entity_tier_price');
        $count = 0;

        foreach ($data['tier_prices'] as $tierPrice) {
            $productId = $productIdMap[$tierPrice['product_sku']] ?? null;
            if (!$productId) {
                continue;
            }

            $allGroups = empty($tierPrice['all_groups']) ? 0 : 1;
            $customerGroupId = 0;
            if (!$allGroups && isset($tierPrice['customer_group_code'])) {
                $customerGroupId = $customerGroupIdMap[$tierPrice['customer_group_code']] ?? 0;
            }

            // Check if tier price exists
            $exists = $connection->fetchOne(
                $connection->select()->from($tierPriceTable, ['value_id'])
                    ->where('entity_id = ?', $productId)
                    ->where('all_groups = ?', $allGroups)
                    ->where('customer_group_id = ?', $customerGroupId)
                    ->where('qty = ?', $tierPrice['qty'])
                    ->where('website_id = ?', $tierPrice['website_id']),
            );

            if (!$exists) {
                $connection->insert($tierPriceTable, [
                    'entity_id' => $productId,
                    'all_groups' => $allGroups,
                    'customer_group_id' => $customerGroupId,
                    'qty' => $tierPrice['qty'],
                    'value' => $tierPrice['value'],
                    'website_id' => $tierPrice['website_id'],
                ]);
                $count++;
            }
        }

        $output->writeln("  Imported {$count} tier prices");
    }

    /**
     * Import custom options from JSON
     */
    private function importCustomOptionsFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing custom options...</info>');

        $json = file_get_contents($sampleDataDir . '/custom_options.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['custom_options'])) {
            $output->writeln('  No custom options to import');
            return;
        }

        $count = 0;

        foreach ($data['custom_options'] as $optionData) {
            // Find product by SKU
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $optionData['product_sku']);
            if (!$product || !$product->getId()) {
                continue;
            }

            // Check if option already exists (by title and type)
            $existingOptions = $product->getProductOptionsCollection();
            $optionExists = false;
            foreach ($existingOptions as $existingOption) {
                if ($existingOption->getTitle() === $optionData['title'] && $existingOption->getType() === $optionData['type']) {
                    $optionExists = true;
                    break;
                }
            }

            if ($optionExists) {
                continue;
            }

            /** @var \Mage_Catalog_Model_Product_Option $option */
            $option = Mage::getModel('catalog/product_option');
            $option->setProduct($product);
            $option->setType($optionData['type']);
            $option->setIsRequire($optionData['is_require']);
            $option->setSortOrder($optionData['sort_order']);
            $option->setTitle($optionData['title']);

            if (!empty($optionData['sku'])) {
                $option->setSku($optionData['sku']);
            }
            if (!empty($optionData['max_characters'])) {
                $option->setMaxCharacters($optionData['max_characters']);
            }
            if (!empty($optionData['file_extension'])) {
                $option->setFileExtension($optionData['file_extension']);
            }
            if (!empty($optionData['image_size_x'])) {
                $option->setImageSizeX($optionData['image_size_x']);
            }
            if (!empty($optionData['image_size_y'])) {
                $option->setImageSizeY($optionData['image_size_y']);
            }
            if (isset($optionData['price'])) {
                $option->setPrice($optionData['price']);
                $option->setPriceType($optionData['price_type'] ?? 'fixed');
            }

            // For select types, add values
            if (!empty($optionData['values'])) {
                $values = [];
                foreach ($optionData['values'] as $valueData) {
                    $values[] = [
                        'title' => $valueData['title'],
                        'price' => $valueData['price'] ?? 0,
                        'price_type' => $valueData['price_type'] ?? 'fixed',
                        'sku' => $valueData['sku'] ?? '',
                        'sort_order' => $valueData['sort_order'] ?? 0,
                    ];
                }
                $option->setValues($values);
            }

            $option->save();
            $count++;
        }

        $output->writeln("  Imported {$count} custom options");
    }

    /**
     * Import dynamic category rules from JSON
     */
    private function importDynamicCategoryRulesFromJson(string $sampleDataDir, OutputInterface $output): void
    {
        $output->writeln('<info>Importing dynamic category rules...</info>');

        $json = file_get_contents($sampleDataDir . '/dynamic_category_rules.json');
        $data = Mage::helper('core')->jsonDecode($json);

        if (empty($data['dynamic_category_rules'])) {
            $output->writeln('  No dynamic category rules to import');
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Build category path to ID map
        $categoryPathMap = $this->buildCategoryPathMap();

        $tableName = $connection->getTableName('catalog_category_dynamic_rule');
        $count = 0;

        foreach ($data['dynamic_category_rules'] as $rule) {
            $categoryId = $categoryPathMap[$rule['category_path']] ?? null;
            if (!$categoryId) {
                continue;
            }

            // Check if rule exists for this category
            $exists = $connection->fetchOne(
                $connection->select()->from($tableName, ['rule_id'])
                    ->where('category_id = ?', $categoryId),
            );

            if ($exists) {
                // Update existing rule
                $connection->update($tableName, [
                    'conditions_serialized' => $rule['conditions_serialized'],
                    'is_active' => $rule['is_active'],
                    'updated_at' => Mage_Core_Model_Locale::now(),
                ], ['rule_id = ?' => $exists]);
            } else {
                // Insert new rule
                $connection->insert($tableName, [
                    'category_id' => $categoryId,
                    'conditions_serialized' => $rule['conditions_serialized'],
                    'is_active' => $rule['is_active'],
                    'created_at' => Mage_Core_Model_Locale::now(),
                    'updated_at' => Mage_Core_Model_Locale::now(),
                ]);
                $count++;
            }

            // Set is_dynamic attribute on the category
            $category = Mage::getModel('catalog/category')->load($categoryId);
            if ($category->getId()) {
                $category->setIsDynamic(1);
                $category->save();
            }
        }

        $output->writeln("  Imported {$count} dynamic category rules");
    }
}
