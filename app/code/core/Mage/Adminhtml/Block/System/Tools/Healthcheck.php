<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Tools_Healthcheck extends Mage_Adminhtml_Block_Template
{
    public function getMahoVersion(): string
    {
        return Mage::getVersion();
    }

    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    public function getPhpSapi(): string
    {
        return PHP_SAPI;
    }

    public function getServerSoftware(): string
    {
        return $_SERVER['SERVER_SOFTWARE'] ?? $this->__('Unknown');
    }

    public function getPhpMemoryLimit(): string
    {
        return ini_get('memory_limit') ?: $this->__('Unknown');
    }

    public function getPhpMaxExecutionTime(): string
    {
        return ini_get('max_execution_time') ?: '0';
    }

    public function getPhpMaxInputVars(): string
    {
        return ini_get('max_input_vars') ?: $this->__('Unknown');
    }

    public function getPhpPostMaxSize(): string
    {
        return ini_get('post_max_size') ?: $this->__('Unknown');
    }

    public function getPhpUploadMaxFilesize(): string
    {
        return ini_get('upload_max_filesize') ?: $this->__('Unknown');
    }

    /**
     * @return array<string, bool>
     */
    public function getPhpExtensions(): array
    {
        $lockFile = MAHO_ROOT_DIR . '/composer.lock';
        if (!file_exists($lockFile)) {
            return [];
        }

        $lockData = json_decode(file_get_contents($lockFile), true);
        $extensions = [];

        // Root platform requirements
        foreach (array_keys($lockData['platform'] ?? []) as $package) {
            if (str_starts_with($package, 'ext-')) {
                $extensions[substr($package, 4)] = true;
            }
        }

        // All package requirements
        foreach ($lockData['packages'] ?? [] as $pkg) {
            foreach (array_keys($pkg['require'] ?? []) as $req) {
                if (str_starts_with($req, 'ext-')) {
                    $extensions[substr($req, 4)] = true;
                }
            }
        }

        ksort($extensions);
        $result = [];
        foreach (array_keys($extensions) as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOpcacheStatus(): ?array
    {
        if (!function_exists('opcache_get_status')) {
            return null;
        }

        $status = @opcache_get_status(false);
        if ($status === false) {
            return ['enabled' => false];
        }

        $config = @opcache_get_configuration();

        $result = [
            'enabled' => $status['opcache_enabled'] ?? false,
            'jit_enabled' => isset($status['jit']['enabled']) && $status['jit']['enabled'],
            'memory_used' => $this->formatBytes((int) ($status['memory_usage']['used_memory'] ?? 0)),
            'memory_free' => $this->formatBytes((int) ($status['memory_usage']['free_memory'] ?? 0)),
            'memory_total' => $config ? $this->formatBytes((int) ($config['directives']['opcache.memory_consumption'] ?? 0)) : $this->__('Unknown'),
            'memory_wasted' => $this->formatBytes((int) ($status['memory_usage']['wasted_memory'] ?? 0)),
            'memory_wasted_pct' => round($status['memory_usage']['current_wasted_percentage'] ?? 0, 1) . '%',
            'hit_rate' => isset($status['opcache_statistics']['opcache_hit_rate'])
                ? round($status['opcache_statistics']['opcache_hit_rate'], 1) . '%'
                : $this->__('N/A'),
            'hits' => (int) ($status['opcache_statistics']['hits'] ?? 0),
            'misses' => (int) ($status['opcache_statistics']['misses'] ?? 0),
            'cached_scripts' => (int) ($status['opcache_statistics']['num_cached_scripts'] ?? 0),
            'max_cached_scripts' => $config ? (int) ($config['directives']['opcache.max_accelerated_files'] ?? 0) : 0,
            'cached_keys' => (int) ($status['opcache_statistics']['num_cached_keys'] ?? 0),
            'max_cached_keys' => (int) ($status['opcache_statistics']['max_cached_keys'] ?? 0),
            'interned_strings_used' => isset($status['interned_strings_usage']['used_memory'])
                ? $this->formatBytes((int) $status['interned_strings_usage']['used_memory']) : null,
            'interned_strings_free' => isset($status['interned_strings_usage']['free_memory'])
                ? $this->formatBytes((int) $status['interned_strings_usage']['free_memory']) : null,
            'interned_strings_count' => (int) ($status['interned_strings_usage']['number_of_strings'] ?? 0),
        ];

        if (isset($status['jit']['buffer_size'])) {
            $result['jit_buffer_size'] = $this->formatBytes((int) $status['jit']['buffer_size']);
            $result['jit_buffer_free'] = $this->formatBytes((int) ($status['jit']['buffer_free'] ?? 0));
        }

        $result['directives'] = $config ? $config['directives'] : [];

        return $result;
    }

    public function getDatabaseType(): string
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        return match (true) {
            $adapter instanceof \Maho\Db\Adapter\Pdo\Mysql => 'MySQL/MariaDB',
            $adapter instanceof \Maho\Db\Adapter\Pdo\Pgsql => 'PostgreSQL',
            $adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite => 'SQLite',
            is_object($adapter) => $adapter::class,
            default => $this->__('Unknown'),
        };
    }

    public function getDatabaseVersion(): string
    {
        try {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

            if ($adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite) {
                return (string) $adapter->fetchOne('SELECT sqlite_version()');
            }

            return (string) $adapter->fetchOne('SELECT VERSION()');
        } catch (\Exception) {
            return $this->__('Unable to retrieve');
        }
    }

    public function getDatabaseSize(): string
    {
        try {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

            if ($adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite) {
                $pageCount = (int) $adapter->fetchOne('PRAGMA page_count');
                $pageSize = (int) $adapter->fetchOne('PRAGMA page_size');
                return round(($pageCount * $pageSize) / 1024 / 1024, 2) . ' MB';
            }

            if ($adapter instanceof \Maho\Db\Adapter\Pdo\Pgsql) {
                $dbName = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/dbname');
                $size = $adapter->fetchOne(
                    'SELECT pg_size_pretty(pg_database_size(?))',
                    [$dbName],
                );
                return $size ?: $this->__('Unable to retrieve');
            }

            $dbName = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/dbname');
            $size = $adapter->fetchOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) '
                . 'FROM information_schema.tables WHERE table_schema = ?',
                [$dbName],
            );
            return $size ? $size . ' MB' : $this->__('Unable to retrieve');
        } catch (\Exception) {
            return $this->__('Unable to retrieve');
        }
    }

    public function getCacheBackend(): string
    {
        $cacheNode = Mage::getConfig()->getNode('global/cache/backend');
        return $cacheNode ? (string) $cacheNode : 'File';
    }

    public function getSessionSaveMethod(): string
    {
        return (string) (Mage::getConfig()->getNode('global/session_save') ?: 'files');
    }

    public function getOperatingSystem(): string
    {
        return PHP_OS . ' ' . php_uname('r');
    }

    public function getArchitecture(): string
    {
        return php_uname('m');
    }

    /**
     * @return array<int, array{check: string, status: string, severity: string, details: string}>
     */
    public function getHealthChecks(): array
    {
        $checks = [];

        $isOptimized = \MahoCLI\Commands\HealthCheck::isComposerAutoloaderOptimized();
        $checks[] = [
            'check' => $this->__('Composer Autoloader'),
            'status' => $isOptimized ? $this->__('Warning') : $this->__('OK'),
            'severity' => $isOptimized ? 'warning' : 'ok',
            'details' => $isOptimized ? $this->__('Optimized autoloader detected. This is fine for production, but may cause issues during development. Run "composer dump" to fix.') : '',
        ];

        $legacyFiles = \MahoCLI\Commands\HealthCheck::findExistingPaths(\MahoCLI\Commands\HealthCheck::LEGACY_CORE_FILES);
        $checks[] = [
            'check' => $this->__('Legacy Core Files'),
            'status' => empty($legacyFiles) ? $this->__('OK') : $this->__('Error'),
            'severity' => empty($legacyFiles) ? 'ok' : 'error',
            'details' => empty($legacyFiles) ? '' : $this->__('Found old Magento/OpenMage files: %s. These should be removed.', implode(', ', $legacyFiles)),
        ];

        $deprecatedFolders = \MahoCLI\Commands\HealthCheck::findExistingPaths(\MahoCLI\Commands\HealthCheck::DEPRECATED_FOLDERS);
        $checks[] = [
            'check' => $this->__('Deprecated Folders'),
            'status' => empty($deprecatedFolders) ? $this->__('OK') : $this->__('Error'),
            'severity' => empty($deprecatedFolders) ? 'ok' : 'error',
            'details' => empty($deprecatedFolders) ? '' : $this->__('Found deprecated folders: %s. Remove them to avoid unpredictable behavior.', implode(', ', $deprecatedFolders)),
        ];

        foreach (['admin' => $this->__('Admin'), 'api' => $this->__('API')] as $type => $label) {
            try {
                $orphanedIds = \MahoCLI\Commands\HealthCheck::findOrphanedResourceIds($type);
                $checks[] = [
                    'check' => $this->__('%s Orphaned Role Resources', $label),
                    'status' => empty($orphanedIds) ? $this->__('OK') : $this->__('Warning'),
                    'severity' => empty($orphanedIds) ? 'ok' : 'warning',
                    'details' => empty($orphanedIds) ? '' : $this->__('Found %d orphaned resource(s): %s', count($orphanedIds), implode(', ', $orphanedIds)),
                ];
            } catch (\Exception) {
                $checks[] = [
                    'check' => $this->__('%s Orphaned Role Resources', $label),
                    'status' => $this->__('Error'),
                    'severity' => 'error',
                    'details' => $this->__('Unable to check orphaned resources.'),
                ];
            }
        }

        return $checks;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
