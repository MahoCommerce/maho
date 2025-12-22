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

        // 2. Import config data
        if (file_exists($sampleDataDir . '/config.json')) {
            $this->importConfigFromJson($sampleDataDir, $output);
        }

        // 2b. Import permission_block
        if (file_exists($sampleDataDir . '/permission_block.json')) {
            $this->importPermissionBlockFromJson($sampleDataDir, $output);
        }

        // 3. Create attribute sets
        if (file_exists($sampleDataDir . '/attribute_sets.json')) {
            $this->createAttributeSetsFromJson($sampleDataDir, $output);
        }

        // 4. Create custom attributes
        if (file_exists($sampleDataDir . '/attributes.json')) {
            $this->createAttributesFromJson($sampleDataDir, $output);
        }

        // 5. Import categories
        if (file_exists($sampleDataDir . '/categories.json')) {
            $this->importCategoriesFromJson($sampleDataDir, $output);
        }

        // 6. Import products
        if (file_exists($sampleDataDir . '/products.json')) {
            $this->importProductsFromJson($sampleDataDir, $output);
        }

        // 7. Import CMS content
        if (file_exists($sampleDataDir . '/cms.json')) {
            $this->importCmsFromJson($sampleDataDir, $output);
        }

        // 8. Import blog posts
        if (file_exists($sampleDataDir . '/blog.json')) {
            $this->importBlogFromJson($sampleDataDir, $output);
        }

        // 9. Import reviews
        if (file_exists($sampleDataDir . '/reviews.json')) {
            $this->importReviewsFromJson($sampleDataDir, $output);
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
        $setCount = 0;

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

            // Process groups and attribute assignments
            foreach ($setData['groups'] ?? [] as $groupData) {
                // Find or create the group
                /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection $existingGroups */
                $existingGroups = Mage::getModel('eav/entity_attribute_group')->getCollection()
                    ->setAttributeSetFilter($attributeSet->getId())
                    ->addFieldToFilter('attribute_group_name', $groupData['name']);

                if ($existingGroups->getSize() > 0) {
                    $group = $existingGroups->getFirstItem();
                } else {
                    /** @var \Mage_Eav_Model_Entity_Attribute_Group $group */
                    $group = Mage::getModel('eav/entity_attribute_group');
                    $group->setAttributeSetId($attributeSet->getId());
                    $group->setAttributeGroupName($groupData['name']);
                    $group->setSortOrder($groupData['sort_order'] ?? 0);
                    $group->save();
                }

                // Assign attributes to this group
                foreach ($groupData['attributes'] ?? [] as $attrData) {
                    $attribute = Mage::getModel('eav/entity_attribute')
                        ->loadByCode($entityTypeId, $attrData['code']);

                    if ($attribute->getId()) {
                        /** @var \Mage_Eav_Model_Entity_Attribute $attribute */
                        $attribute->setAttributeSetId($attributeSet->getId());
                        $attribute->setAttributeGroupId($group->getId());
                        $attribute->setSortOrder($attrData['sort_order'] ?? 0);
                        $attribute->save();
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

        /** @var \Mage_Catalog_Model_Resource_Setup $installer */
        $installer = Mage::getResourceModel('catalog/setup', ['resourceName' => 'core_setup']);
        $entityTypeId = $installer->getEntityTypeId('catalog_product');
        $attributeCount = 0;

        foreach ($data['catalog_product'] as $attributeCode => $config) {
            // Check if attribute already exists
            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode($entityTypeId, $attributeCode);

            if ($attribute->getId()) {
                continue; // Skip existing attributes
            }

            $attributeData = [
                'type' => $config['type'] ?? 'varchar',
                'input' => $config['input'] ?? 'text',
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
                'is_configurable' => $config['is_configurable'] ?? false,
            ];

            // Add options if present
            if (!empty($config['option']['values'])) {
                $optionValues = [];
                foreach ($config['option']['values'] as $value) {
                    $optionValues[] = $value;
                }
                $attributeData['option'] = ['values' => $optionValues];
            }

            $installer->addAttribute('catalog_product', $attributeCode, $attributeData);
            $attributeCount++;
        }

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

            if ($existing->getSize() > 0) {
                $category = $existing->getFirstItem();
            } else {
                /** @var Mage_Catalog_Model_Category $category */
                $category = Mage::getModel('catalog/category');
                $category->setStoreId($storeId);

                $category->setData([
                    'name' => $catData['name'],
                    'url_key' => $catData['url_key'],
                    'is_active' => $catData['is_active'] ?? 1,
                    'include_in_menu' => $catData['include_in_menu'] ?? 1,
                    'description' => $catData['description'] ?? '',
                    'image' => $catData['image'] ?? null,
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
     * Import products from JSON
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

        // Build category path to ID mapping
        $categoryMap = $this->buildCategoryPathMap();

        // First pass: create simple products (needed for configurable/bundle/grouped)
        $simpleCount = 0;
        $complexCount = 0;

        foreach ($data['products'] as $productData) {
            if (in_array($productData['type'], ['simple', 'virtual', 'downloadable'])) {
                $this->createProduct($productData, $categoryMap, $sampleDataDir);
                $simpleCount++;
            }
        }

        // Second pass: create complex products
        foreach ($data['products'] as $productData) {
            if (in_array($productData['type'], ['configurable', 'bundle', 'grouped'])) {
                $this->createProduct($productData, $categoryMap, $sampleDataDir);
                $complexCount++;
            }
        }

        $total = $simpleCount + $complexCount;
        $output->writeln("  Imported {$total} products ({$simpleCount} simple, {$complexCount} complex)");
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
                // Use default website
                $websiteIds[] = Mage::app()->getDefaultStoreView()->getWebsiteId();
            }
        }

        // Get category IDs
        $categoryIds = [];
        foreach ($productData['categories'] ?? [] as $categoryPath) {
            if (isset($categoryMap[$categoryPath])) {
                $categoryIds[] = $categoryMap[$categoryPath];
            }
        }

        $product->setData([
            'sku' => $productData['sku'],
            'type_id' => $productData['type'],
            'attribute_set_id' => $attributeSetId,
            'name' => $productData['name'],
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

        $product->save();

        // Handle images after save
        if (!empty($productData['images'])) {
            $this->addProductImages($product, $productData['images'], $sampleDataDir);
        }

        // Handle product type specific data
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
            case 'downloadable':
                $this->setupDownloadableProduct($product, $productData);
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
        $mediaDir = $sampleDataDir . '/media/catalog/product';

        // Add main images
        foreach (['image', 'small_image', 'thumbnail'] as $imageType) {
            if (!empty($images[$imageType])) {
                $imagePath = $mediaDir . $images[$imageType];
                if (file_exists($imagePath)) {
                    try {
                        $product->addImageToMediaGallery(
                            $imagePath,
                            [$imageType],
                            false,
                            false,
                        );
                    } catch (Exception $e) {
                        // Image might already exist
                    }
                }
            }
        }

        // Add gallery images
        if (!empty($images['gallery'])) {
            foreach ($images['gallery'] as $galleryImage) {
                $imagePath = $mediaDir . $galleryImage;
                if (file_exists($imagePath)) {
                    try {
                        $product->addImageToMediaGallery($imagePath, null, false, false);
                    } catch (Exception $e) {
                        // Image might already exist
                    }
                }
            }
        }

        $product->save();
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupConfigurableProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['configurable_attributes']) || empty($productData['associated_skus'])) {
            return;
        }

        // Get configurable attribute IDs
        $attributeIds = [];
        foreach ($productData['configurable_attributes'] as $attrCode) {
            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode('catalog_product', $attrCode);
            if ($attribute->getId()) {
                $attributeIds[] = $attribute->getId();
            }
        }

        if (empty($attributeIds)) {
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

        /** @var \Mage_Catalog_Model_Product_Type_Configurable $configurableType */
        $configurableType = $product->getTypeInstance(true);
        $configurableType->setUsedProductAttributeIds($attributeIds, $product);

        $configurableAttributesData = $configurableType->getConfigurableAttributesAsArray($product);
        $product->setConfigurableAttributesData($configurableAttributesData);

        // Build configurable products data
        $configurableProductsData = [];
        foreach ($associatedProductIds as $productId) {
            $configurableProductsData[$productId] = [];
        }
        $product->setConfigurableProductsData($configurableProductsData);

        $product->save();
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupBundleProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['bundle_options'])) {
            return;
        }

        $product->setPriceType($productData['price_type'] === 'fixed' ? 1 : 0);

        $bundleOptions = [];
        $bundleSelections = [];

        foreach ($productData['bundle_options'] as $optionIndex => $optionData) {
            $bundleOptions[$optionIndex] = [
                'title' => $optionData['title'],
                'type' => $optionData['type'],
                'required' => $optionData['required'],
                'position' => $optionIndex,
                'delete' => '',
            ];

            $selections = [];
            foreach ($optionData['products'] as $selectionIndex => $selectionData) {
                $simpleProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $selectionData['sku']);
                if ($simpleProduct && $simpleProduct->getId()) {
                    $selections[$selectionIndex] = [
                        'product_id' => $simpleProduct->getId(),
                        'selection_qty' => $selectionData['qty'],
                        'selection_can_change_qty' => 1,
                        'is_default' => $selectionData['is_default'] ?? 0,
                        'position' => $selectionIndex,
                        'delete' => '',
                    ];
                }
            }
            $bundleSelections[$optionIndex] = $selections;
        }

        $product->setBundleOptionsData($bundleOptions);
        $product->setBundleSelectionsData($bundleSelections);
        $product->setCanSaveCustomOptions(true);
        $product->setCanSaveBundleSelections(true);

        $product->save();
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupGroupedProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['associated_products'])) {
            return;
        }

        $links = [];
        foreach ($productData['associated_products'] as $assocData) {
            $simpleProduct = Mage::getModel('catalog/product')->loadByAttribute('sku', $assocData['sku']);
            if ($simpleProduct && $simpleProduct->getId()) {
                /** @var \Mage_Catalog_Model_Product_Link $link */
                $link = Mage::getModel('catalog/product_link');
                $link->setProductId($product->getId());
                $link->setLinkedProductId($simpleProduct->getId());
                $link->setLinkTypeId(\Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
                $links[] = $link;
            }
        }

        if (!empty($links)) {
            $product->setGroupedLinkData($links);
            $product->save();
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function setupDownloadableProduct(Mage_Catalog_Model_Product $product, array $productData): void
    {
        if (empty($productData['links'])) {
            return;
        }

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
        $product->save();
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
}
