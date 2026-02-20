<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use MahoCLI\Helper\SampleDataImporter;
use MahoCLI\Helper\SqlConverter;

/**
 * Sample Data Installer Model
 *
 * Orchestrates sample data installation with progress tracking.
 * Reuses SampleDataImporter and SqlConverter from CLI for maximum code reuse.
 */
class Mage_Install_Model_Installer_SampleData
{
    private const PROGRESS_FILE = 'sampledata_progress.json';

    private const SAMPLE_DATA_URL_TEMPLATE = 'https://github.com/MahoCommerce/maho-sample-data/archive/refs/heads/%s.tar.gz';

    private ?string $tempFile = null;
    private ?string $sampleDataDir = null;

    /**
     * Get the progress file path
     */
    public function getProgressFilePath(): string
    {
        return Mage::getBaseDir('var') . DS . self::PROGRESS_FILE;
    }

    /**
     * Get current installation progress
     */
    public function getProgress(): array
    {
        $progressFile = $this->getProgressFilePath();
        if (!file_exists($progressFile)) {
            return [
                'phase' => 'idle',
                'percent' => 0,
                'message' => '',
                'error' => null,
            ];
        }

        $content = file_get_contents($progressFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [
            'phase' => 'idle',
            'percent' => 0,
            'message' => '',
            'error' => null,
        ];
    }

    /**
     * Check if installation is currently in progress
     */
    public function isInstalling(): bool
    {
        $progress = $this->getProgress();
        return !in_array($progress['phase'], ['idle', 'complete', 'error'], true);
    }

    /**
     * Clear progress file
     */
    public function clearProgress(): void
    {
        $progressFile = $this->getProgressFilePath();
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }
    }

    /**
     * Update installation progress
     */
    public function updateProgress(string $phase, int $percent, string $message, ?string $error = null): void
    {
        $data = [
            'phase' => $phase,
            'percent' => $percent,
            'message' => $message,
            'error' => $error,
            'updated_at' => time(),
        ];

        file_put_contents(
            $this->getProgressFilePath(),
            json_encode($data, JSON_PRETTY_PRINT),
        );
    }

    /**
     * Main installation entry point
     */
    public function install(): bool
    {
        try {
            $this->updateProgress('downloading', 0, Mage::helper('install')->__('Downloading sample data...'));
            $this->tempFile = $this->downloadSampleData();

            $this->updateProgress('extracting', 40, Mage::helper('install')->__('Extracting files...'));
            $this->sampleDataDir = $this->extractArchive($this->tempFile);

            $this->updateProgress('copying_media', 50, Mage::helper('install')->__('Copying media files...'));
            $this->copyMediaFiles($this->sampleDataDir);

            $this->updateProgress('importing_data', 60, Mage::helper('install')->__('Importing database...'));
            $this->importDatabase($this->sampleDataDir);

            $this->updateProgress('importing_config', 70, Mage::helper('install')->__('Importing configuration...'));
            $this->importConfig($this->sampleDataDir);
            $this->importBlogPosts($this->sampleDataDir);
            $this->cleanup();

            $this->updateProgress('reindexing', 80, Mage::helper('install')->__('Reindexing data...'));
            $this->reindexAll();

            $this->updateProgress('cache_flush', 95, Mage::helper('install')->__('Flushing caches...'));
            $this->clearCaches();

            $this->updateProgress('complete', 100, Mage::helper('install')->__('Sample data installed successfully!'));
            return true;
        } catch (Exception $e) {
            $this->updateProgress(
                'error',
                $this->getProgress()['percent'] ?? 0,
                Mage::helper('install')->__('Installation failed'),
                $e->getMessage(),
            );
            $this->cleanup();
            throw $e;
        }
    }

    /**
     * Download sample data tarball from GitHub
     */
    private function downloadSampleData(): string
    {
        $mahoVersion = Mage::getVersion();
        $versionParts = explode('.', $mahoVersion);
        $branchVersion = "{$versionParts[0]}.{$versionParts[1]}";

        $url = sprintf(self::SAMPLE_DATA_URL_TEMPLATE, $branchVersion);
        $tempFile = tempnam(sys_get_temp_dir(), 'maho_sample_data');

        $content = file_get_contents($url);
        if ($content === false) {
            throw new Mage_Core_Exception(
                Mage::helper('install')->__('Failed to download sample data from %s', $url),
            );
        }

        if (file_put_contents($tempFile, $content) === false) {
            throw new Mage_Core_Exception(
                Mage::helper('install')->__('Failed to save sample data to temporary file'),
            );
        }

        return $tempFile;
    }

    /**
     * Extract the downloaded archive
     */
    private function extractArchive(string $tempFile): string
    {
        $targetDir = Mage::getBaseDir();

        $extractCommand = sprintf('tar -xzf %s -C %s 2>&1', escapeshellarg($tempFile), escapeshellarg($targetDir));
        exec($extractCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Mage_Core_Exception(
                Mage::helper('install')->__('Failed to extract sample data: %s', implode("\n", $output)),
            );
        }

        $mahoVersion = Mage::getVersion();
        $versionParts = explode('.', $mahoVersion);
        $branchVersion = "{$versionParts[0]}.{$versionParts[1]}";

        return $targetDir . DS . "maho-sample-data-{$branchVersion}";
    }

    /**
     * Copy media files from sample data to public/media
     */
    private function copyMediaFiles(string $sampleDataDir): void
    {
        $sourceMediaDir = $sampleDataDir . DS . 'media';
        $targetMediaDir = Mage::getBaseDir() . DS . 'public' . DS . 'media';

        if (!is_dir($sourceMediaDir)) {
            return;
        }

        $copyCommand = sprintf(
            'cp -R %s/* %s/ 2>&1',
            escapeshellarg($sourceMediaDir),
            escapeshellarg($targetMediaDir),
        );
        exec($copyCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Mage_Core_Exception(
                Mage::helper('install')->__('Failed to copy media files: %s', implode("\n", $output)),
            );
        }
    }

    /**
     * Import database with attribute ID remapping
     */
    private function importDatabase(string $sampleDataDir): void
    {
        $dataFilePath = $sampleDataDir . DS . 'db_data.sql';
        if (!file_exists($dataFilePath)) {
            return;
        }

        $pdo = $this->getPdo();
        $dbEngine = $this->getDbEngine();

        $dataSql = file_get_contents($dataFilePath);

        $logCallback = function (string $message, string $level = 'info'): void {
            // Silent logging during web installation
        };

        $importer = new SampleDataImporter($pdo, $logCallback);
        $remappedSql = $importer->import($dataSql);

        // Execute the remapped SQL
        $this->executeSqlForEngine($pdo, $remappedSql, $dbEngine);

        // Merge sample data's attribute groups first (builds group ID remap)
        $importer->mergeAttributeGroups();

        // Merge sample data's attribute set assignments (uses the group ID remap)
        $importer->mergeEntityAttributes();

        // Store importer for config remapping
        $this->currentImporter = $importer;
    }

    private ?SampleDataImporter $currentImporter = null;

    /**
     * Import configuration with attribute ID remapping
     */
    private function importConfig(string $sampleDataDir): void
    {
        $configFilePath = $sampleDataDir . DS . 'db_config.sql';
        if (!file_exists($configFilePath) || $this->currentImporter === null) {
            return;
        }

        $pdo = $this->getPdo();
        $dbEngine = $this->getDbEngine();

        $configSql = file_get_contents($configFilePath);
        $remappedConfigSql = $this->currentImporter->remapConfigValuesOnly($configSql);

        $this->executeSqlForEngine($pdo, $remappedConfigSql, $dbEngine);

        // Update PostgreSQL sequences if needed
        if ($dbEngine === 'pgsql') {
            $this->updatePostgresSequences($pdo);
        }
    }

    /**
     * Import blog posts if Maho_Blog module is enabled
     */
    private function importBlogPosts(string $sampleDataDir): void
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            return;
        }

        $csvPath = $sampleDataDir . DS . 'blog_posts_en.csv';
        if (!file_exists($csvPath)) {
            return;
        }

        try {
            $handle = fopen($csvPath, 'r');
            if ($handle === false) {
                return;
            }

            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                return;
            }

            $storeId = Mage::app()->getStore()->getId();

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    continue;
                }

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
            }

            fclose($handle);
        } catch (Exception $e) {
            // Non-critical, continue installation
        }
    }

    /**
     * Clean up temporary files
     */
    private function cleanup(): void
    {
        if ($this->tempFile !== null && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        if ($this->sampleDataDir !== null && is_dir($this->sampleDataDir)) {
            $rmCommand = sprintf('rm -rf %s 2>&1', escapeshellarg($this->sampleDataDir));
            exec($rmCommand);
        }

        $this->tempFile = null;
        $this->sampleDataDir = null;
    }

    /**
     * Reindex all indexers
     */
    private function reindexAll(): void
    {
        /** @var Mage_Index_Model_Resource_Process_Collection $indexCollection */
        $indexCollection = Mage::getResourceModel('index/process_collection');

        foreach ($indexCollection as $index) {
            /** @var Mage_Index_Model_Process $index */
            if ($index->isLocked()) {
                $index->unlock();
            }
            $index->reindexEverything();
        }
    }

    /**
     * Clear all caches
     */
    private function clearCaches(): void
    {
        Mage::app()->getCache()->flush();
    }

    /**
     * Get PDO connection from Mage resource
     */
    private function getPdo(): \PDO
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        return $connection->getConnection()->getNativeConnection();
    }

    /**
     * Get database engine type
     */
    private function getDbEngine(): string
    {
        $config = Mage::getConfig()->getNode('global/resources/default_setup/connection');
        return (string) ($config->engine ?? 'mysql');
    }

    /**
     * Execute SQL content for the specified database engine
     */
    private function executeSqlForEngine(\PDO $pdo, string $sql, string $dbEngine): void
    {
        $converter = new SqlConverter();
        $converter->setPdo($pdo);

        // Progress ranges from 60% (importing_data start) to 70% (importing_config start)
        $progressCallback = function ($current, $total): void {
            $percent = 60 + (int) (($current / max($total, 1)) * 10);
            $this->updateProgress(
                'importing_data',
                $percent,
                Mage::helper('install')->__('Importing database... (%s/%s)', $current, $total),
            );
        };

        if ($dbEngine === 'pgsql') {
            $pdo->exec('SET session_replication_role = replica');
            $convertedSql = $converter->mysqlToPostgresql($sql);
            $converter->executeStatements($pdo, $convertedSql, $progressCallback);
            $pdo->exec('SET session_replication_role = DEFAULT');
        } elseif ($dbEngine === 'sqlite') {
            // Disable foreign keys for bulk import (adapter enables them by default)
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $convertedSql = $converter->mysqlToSqlite($sql);
            $converter->executeStatements($pdo, $convertedSql, $progressCallback);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            // MySQL - disable foreign key checks for bulk import
            // Don't use executeStatements() as it adds PostgreSQL-specific ON CONFLICT syntax
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->executeMysqlStatements($pdo, $sql, $progressCallback);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Execute MySQL statements with progress tracking (without ON CONFLICT conversion)
     */
    private function executeMysqlStatements(\PDO $pdo, string $sql, ?callable $progressCallback = null): void
    {
        // Split SQL into individual statements
        $statements = preg_split('/;\s*$/m', $sql);
        $statements = array_filter(array_map('trim', $statements));
        $total = count($statements);
        $current = 0;

        foreach ($statements as $statement) {
            if (empty($statement)) {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                $shortStatement = substr($statement, 0, 100);
                throw new \PDOException(
                    "Failed to execute: {$shortStatement}... Error: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e,
                );
            }

            $current++;
            if ($progressCallback && $current % 100 === 0) {
                $progressCallback($current, $total);
            }
        }

        if ($progressCallback) {
            $progressCallback($total, $total);
        }
    }

    /**
     * Update PostgreSQL sequences after import
     */
    private function updatePostgresSequences(\PDO $pdo): void
    {
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
                $maxStmt = $pdo->query("SELECT COALESCE(MAX(\"{$columnName}\"), 0) as max_id FROM \"{$tableName}\"");
                $maxId = (int) $maxStmt->fetchColumn();

                if ($maxId > 0) {
                    $pdo->exec("SELECT setval('\"{$sequenceName}\"', {$maxId}, true)");
                }
            } catch (\PDOException $e) {
                continue;
            }
        }
    }
}
