<?php

declare(strict_types=1);

/**
 * Maho Pest Test Runner with Automatic Test Database Setup
 *
 * This script:
 * 1. Backs up current local.xml
 * 2. Creates a fresh test database with sample data
 * 3. Runs Pest tests
 * 4. Restores original local.xml
 *
 * Database credentials are resolved in this order:
 * 1. Environment variables (if set)
 * 2. local.xml (if configured for the same database type being tested)
 *
 * Environment variables:
 * - MAHO_DB_TYPE: Database type ('mysql' or 'pgsql'), defaults to 'mysql'
 * - MAHO_MYSQL_HOST, MAHO_MYSQL_USER, MAHO_MYSQL_PASS, MAHO_MYSQL_DBNAME: MySQL credentials
 * - MAHO_PGSQL_HOST, MAHO_PGSQL_USER, MAHO_PGSQL_PASS, MAHO_PGSQL_DBNAME: PostgreSQL credentials
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class PestTestRunner
{
    private const LOCAL_XML_PATH = 'app/etc/local.xml';
    private const LOCAL_XML_BACKUP = 'app/etc/local.xml.backup';

    private array $dbConfig = [];
    private string $testDbName;
    private string $dbType;

    public function __construct()
    {
        $this->dbType = getenv('MAHO_DB_TYPE') ?: 'mysql';
        $this->loadDatabaseConfig();
        $this->testDbName = $this->dbConfig['name'] . '_test';
    }

    public function run(array $pestArgs = []): int
    {
        echo "Setting up fresh test database for local testing ({$this->dbType})...\n";

        try {
            $this->backupLocalXml();
            $this->setupTestDatabase();
            $exitCode = $this->runPest($pestArgs);

            // Flush cache after tests complete
            echo "\nFlushing cache after tests...\n";
            $this->executeCommand('./maho cache:flush --ansi');
            echo "✓ Cache flushed\n";

            return $exitCode;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            return 1;
        } finally {
            $this->restoreLocalXml();
        }
    }

    private function loadDatabaseConfig(): void
    {
        // Check environment variables first (for both MySQL and PostgreSQL)
        if ($this->dbType === 'pgsql') {
            if (getenv('MAHO_PGSQL_HOST') || getenv('MAHO_PGSQL_USER')) {
                $this->dbConfig = [
                    'host' => getenv('MAHO_PGSQL_HOST') ?: 'localhost',
                    'user' => getenv('MAHO_PGSQL_USER') ?: 'postgres',
                    'pass' => getenv('MAHO_PGSQL_PASS') ?: '',
                    'name' => getenv('MAHO_PGSQL_DBNAME') ?: 'maho',
                ];
                return;
            }
        } else {
            if (getenv('MAHO_MYSQL_HOST') || getenv('MAHO_MYSQL_USER')) {
                $this->dbConfig = [
                    'host' => getenv('MAHO_MYSQL_HOST') ?: 'localhost',
                    'user' => getenv('MAHO_MYSQL_USER') ?: 'root',
                    'pass' => getenv('MAHO_MYSQL_PASS') ?: '',
                    'name' => getenv('MAHO_MYSQL_DBNAME') ?: 'maho',
                ];
                return;
            }
        }

        // Fall back to reading from existing local.xml
        if (!file_exists(self::LOCAL_XML_PATH)) {
            $envPrefix = $this->dbType === 'pgsql' ? 'MAHO_PGSQL_*' : 'MAHO_MYSQL_*';
            throw new Exception("local.xml not found. Please set {$envPrefix} environment variables or install Maho first.");
        }

        $xml = simplexml_load_file(self::LOCAL_XML_PATH);
        if ($xml === false) {
            throw new Exception('Could not parse local.xml');
        }

        $connection = $xml->global->resources->default_setup->connection;
        $configuredDbType = (string) $connection->model;

        // Check if local.xml matches the requested database type
        if ($this->dbType === 'pgsql' && $configuredDbType !== 'pgsql') {
            throw new Exception('local.xml is configured for MySQL. Please set MAHO_PGSQL_* environment variables for PostgreSQL testing.');
        }
        if ($this->dbType === 'mysql' && $configuredDbType === 'pgsql') {
            throw new Exception('local.xml is configured for PostgreSQL. Please set MAHO_MYSQL_* environment variables for MySQL testing.');
        }

        $this->dbConfig = [
            'host' => (string) $connection->host,
            'user' => (string) $connection->username,
            'pass' => (string) $connection->password,
            'name' => (string) $connection->dbname,
        ];
    }

    private function backupLocalXml(): void
    {
        if (file_exists(self::LOCAL_XML_PATH)) {
            if (!copy(self::LOCAL_XML_PATH, self::LOCAL_XML_BACKUP)) {
                throw new Exception('Failed to backup local.xml');
            }
            echo "✓ Backed up local.xml\n";
        }
    }

    private function restoreLocalXml(): void
    {
        if (file_exists(self::LOCAL_XML_BACKUP)) {
            if (!copy(self::LOCAL_XML_BACKUP, self::LOCAL_XML_PATH)) {
                echo "Warning: Failed to restore local.xml from backup\n";
                return;
            }
            unlink(self::LOCAL_XML_BACKUP);
            echo "✓ Restored original local.xml\n";
        }
    }

    private function setupTestDatabase(): void
    {
        echo 'Creating fresh test database: ' . $this->testDbName . "\n";

        if ($this->dbType === 'pgsql') {
            $this->setupPostgresDatabase();
        } else {
            $this->setupMysqlDatabase();
        }
    }

    private function setupMysqlDatabase(): void
    {
        // Drop existing test database
        $this->executeCommand($this->getMysqlCommand('DROP DATABASE IF EXISTS `' . $this->testDbName . '`;'));

        // Create new test database
        $this->executeCommand($this->getMysqlCommand('CREATE DATABASE `' . $this->testDbName . '`;'));
        echo "✓ Created test database\n";

        $this->installMaho('mysql');
    }

    private function setupPostgresDatabase(): void
    {
        // Drop and recreate test database
        $this->executeCommand($this->getPsqlCommand("DROP DATABASE IF EXISTS \"{$this->testDbName}\";", 'postgres'));
        $this->executeCommand($this->getPsqlCommand("CREATE DATABASE \"{$this->testDbName}\";", 'postgres'));
        echo "✓ Created test database\n";

        $this->installMaho('pgsql');
    }

    private function installMaho(string $dbEngine): void
    {
        // Temporarily move local.xml so Maho thinks it's not installed
        $tempLocalXml = self::LOCAL_XML_PATH . '.temp';
        if (file_exists(self::LOCAL_XML_PATH)) {
            rename(self::LOCAL_XML_PATH, $tempLocalXml);
        }

        try {
            // Install Maho with sample data for both MySQL and PostgreSQL
            $sampleData = ' --sample_data 1';

            $installCmd = './maho install --ansi' .
            ' --license_agreement_accepted yes' .
            ' --locale en_US' .
            ' --timezone Europe/London' .
            ' --default_currency USD' .
            ' --db_host ' . escapeshellarg($this->dbConfig['host']) .
            ' --db_name ' . escapeshellarg($this->testDbName) .
            ' --db_user ' . escapeshellarg($this->dbConfig['user']) .
            ' --db_pass ' . escapeshellarg($this->dbConfig['pass']) .
            ' --db_engine ' . escapeshellarg($dbEngine) .
            ' --url http://maho.test/' .
            ' --secure_base_url http://maho.test/' .
            ' --use_secure 0' .
            ' --use_secure_admin 0' .
            ' --admin_lastname admin' .
            ' --admin_firstname admin' .
            ' --admin_email admin@test.com' .
            ' --admin_username admin' .
            ' --admin_password testpassword123' .
            $sampleData;

            echo 'Installing Maho' . ($sampleData ? ' with sample data' : '') . "...\n";
            $this->executeCommand($installCmd);
            echo "✓ Installed Maho\n";

            // Reindex and flush cache
            echo "Reindexing and flushing cache...\n";
            $this->executeCommand('./maho index:reindex:all --ansi');
            $this->executeCommand('./maho cache:flush --ansi');
            echo "✓ Completed setup\n";

        } finally {
            // Clean up temp file
            if (file_exists($tempLocalXml)) {
                unlink($tempLocalXml);
            }
        }
    }

    private function getMysqlCommand(string $sql): string
    {
        $cmd = 'mysql -h ' . escapeshellarg($this->dbConfig['host']) .
               ' -u ' . escapeshellarg($this->dbConfig['user']);

        if (!empty($this->dbConfig['pass'])) {
            $cmd .= ' -p' . escapeshellarg($this->dbConfig['pass']);
        }

        return $cmd . ' -e ' . escapeshellarg($sql);
    }

    private function getPsqlBinary(): string
    {
        // Try common psql binary names
        $binaries = ['psql', 'psql-18', 'psql-17', 'psql-16', 'psql-15'];
        foreach ($binaries as $binary) {
            $path = trim(shell_exec("which $binary 2>/dev/null") ?? '');
            if (!empty($path) && is_executable($path)) {
                return $binary;
            }
        }

        // Check common Homebrew paths
        $brewPaths = [
            '/opt/homebrew/bin/psql',
            '/opt/homebrew/bin/psql-18',
            '/usr/local/bin/psql',
        ];
        foreach ($brewPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'psql'; // Default, will fail with clear error if not found
    }

    private function getPsqlCommand(string $sql, ?string $database = null): string
    {
        $db = $database ?? $this->testDbName;
        $psql = $this->getPsqlBinary();
        $cmd = 'PGPASSWORD=' . escapeshellarg($this->dbConfig['pass']) .
               ' ' . $psql . ' -h ' . escapeshellarg($this->dbConfig['host']) .
               ' -U ' . escapeshellarg($this->dbConfig['user']) .
               ' -d ' . escapeshellarg($db);

        return $cmd . ' -c ' . escapeshellarg($sql);
    }

    private function executeCommand(string $command): void
    {
        echo "Running: $command\n";
        flush();

        passthru($command, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Command failed: $command");
        }
    }

    private function runPest(array $args): int
    {
        $pestCmd = './vendor/bin/pest --colors=always';
        if (!empty($args)) {
            $pestCmd .= ' ' . implode(' ', array_map('escapeshellarg', $args));
        }

        echo "\nRunning Pest tests...\n";
        echo "Command: $pestCmd\n\n";

        // Use the same executeCommand method to get colors
        try {
            $this->executeCommand($pestCmd);
            return 0;
        } catch (Exception $e) {
            // Extract exit code from the exception or return 1
            return 1;
        }
    }
}

// Run the test runner
$args = array_slice($argv, 1); // Remove script name from arguments
$runner = new PestTestRunner();
exit($runner->run($args));
