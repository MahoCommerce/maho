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
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class PestTestRunner
{
    private const LOCAL_XML_PATH = 'app/etc/local.xml';
    private const LOCAL_XML_BACKUP = 'app/etc/local.xml.backup';

    private array $dbConfig = [];
    private string $testDbName;

    public function __construct()
    {
        $this->loadDatabaseConfig();
        $this->testDbName = $this->dbConfig['name'] . '_test';
    }

    public function run(array $pestArgs = []): int
    {
        echo "Setting up fresh test database for local testing...\n";

        try {
            $this->backupLocalXml();
            $this->setupTestDatabase();
            $exitCode = $this->runPest($pestArgs);

            // Flush cache after tests complete
            echo "\nFlushing cache after tests...\n";
            $this->executeCommand("./maho cache:flush --ansi");
            echo "✓ Cache flushed\n";

            return $exitCode;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return 1;
        } finally {
            $this->restoreLocalXml();
        }
    }

    private function loadDatabaseConfig(): void
    {
        if (!file_exists(self::LOCAL_XML_PATH)) {
            throw new Exception("local.xml not found. Please install Maho first.");
        }

        $xml = simplexml_load_file(self::LOCAL_XML_PATH);
        if ($xml === false) {
            throw new Exception("Could not parse local.xml");
        }

        $connection = $xml->global->resources->default_setup->connection;
        $this->dbConfig = [
            'host' => (string)$connection->host,
            'user' => (string)$connection->username,
            'pass' => (string)$connection->password,
            'name' => (string)$connection->dbname,
        ];
    }

    private function backupLocalXml(): void
    {
        if (!copy(self::LOCAL_XML_PATH, self::LOCAL_XML_BACKUP)) {
            throw new Exception("Failed to backup local.xml");
        }
        echo "✓ Backed up local.xml\n";
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
        echo "Creating fresh test database: " . $this->testDbName . "\n";

        // Drop existing test database
        $this->executeCommand($this->getMysqlCommand("DROP DATABASE IF EXISTS `" . $this->testDbName . "`;"));

        // Create new test database
        $this->executeCommand($this->getMysqlCommand("CREATE DATABASE `" . $this->testDbName . "`;"));
        echo "✓ Created test database\n";

        // Temporarily move local.xml so Maho thinks it's not installed
        $tempLocalXml = self::LOCAL_XML_PATH . '.temp';
        if (file_exists(self::LOCAL_XML_PATH)) {
            rename(self::LOCAL_XML_PATH, $tempLocalXml);
        }

        try {
            // Install Maho with sample data
            $installCmd = "./maho install --ansi" .
            " --license_agreement_accepted yes" .
            " --locale en_US" .
            " --timezone Europe/London" .
            " --default_currency USD" .
            " --db_host " . escapeshellarg($this->dbConfig['host']) .
            " --db_name " . escapeshellarg($this->testDbName) .
            " --db_user " . escapeshellarg($this->dbConfig['user']) .
            " --db_pass " . escapeshellarg($this->dbConfig['pass']) .
            " --url http://maho.test/" .
            " --secure_base_url http://maho.test/" .
            " --use_secure 0" .
            " --use_secure_admin 0" .
            " --admin_lastname admin" .
            " --admin_firstname admin" .
            " --admin_email admin@test.com" .
            " --admin_username admin" .
            " --admin_password testpassword123" .
            " --sample_data 1";

            echo "Installing Maho with sample data...\n";
            $this->executeCommand($installCmd);
            echo "✓ Installed Maho with sample data\n";

            // Reindex and flush cache
            echo "Reindexing and flushing cache...\n";
            $this->executeCommand("./maho index:reindex:all --ansi");
            $this->executeCommand("./maho cache:flush --ansi");
            echo "✓ Completed setup\n";
        } finally {
            // Restore the temporary local.xml if it exists
            if (file_exists($tempLocalXml)) {
                rename($tempLocalXml, self::LOCAL_XML_PATH);
            }
        }
    }

    private function getMysqlCommand(string $sql): string
    {
        $cmd = "mysql -h " . escapeshellarg($this->dbConfig['host']) .
               " -u " . escapeshellarg($this->dbConfig['user']);

        if (!empty($this->dbConfig['pass'])) {
            $cmd .= " -p" . escapeshellarg($this->dbConfig['pass']);
        }

        return $cmd . " -e " . escapeshellarg($sql);
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
        $pestCmd = "./vendor/bin/pest --colors=always";
        if (!empty($args)) {
            $pestCmd .= " " . implode(" ", array_map('escapeshellarg', $args));
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
