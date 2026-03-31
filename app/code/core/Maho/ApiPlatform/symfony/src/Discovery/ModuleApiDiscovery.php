<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Discovery;

/**
 * Discovers Maho modules that expose API resources via the Api/ convention.
 *
 * Scans each module's Api/ directory for ApiResource DTOs.
 * Returns both paths (for API Platform mapping) and namespace mappings
 * (for autoloading).
 */
final class ModuleApiDiscovery
{
    /** @var array{paths: string[], namespaces: array<string, string>}|null */
    private static ?array $cache = null;

    /**
     * @return array{paths: string[], namespaces: array<string, string>}
     */
    public static function discover(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $paths = [];
        $namespaces = [];

        // Scan all code pools for modules with Api/ directories
        $codePools = [
            'core/Mage' => 'Mage',
            'core/Maho' => 'Maho',
            'community' => null,  // vendor prefix derived from directory structure
            'local' => null,
        ];

        foreach ($codePools as $pool => $vendorPrefix) {
            $basePath = BP . '/app/code/' . $pool;
            if (!is_dir($basePath)) {
                continue;
            }

            if ($vendorPrefix !== null) {
                // Core pool: Maho/ModuleName/Api/
                foreach (new \DirectoryIterator($basePath) as $item) {
                    if ($item->isDot() || !$item->isDir()) {
                        continue;
                    }

                    $moduleName = $item->getFilename();

                    // Skip the ApiPlatform module itself — it uses its own mechanism
                    if ($moduleName === 'ApiPlatform') {
                        continue;
                    }

                    $apiDir = $basePath . '/' . $moduleName . '/Api';

                    if (is_dir($apiDir)) {
                        $paths[] = $apiDir;
                        $namespaces["{$vendorPrefix}\\{$moduleName}\\Api\\"] = $apiDir;
                    }
                }
            } else {
                // Community/local pools: Vendor/ModuleName/Api/
                foreach (new \DirectoryIterator($basePath) as $vendor) {
                    if ($vendor->isDot() || !$vendor->isDir()) {
                        continue;
                    }
                    $vendorName = $vendor->getFilename();
                    $vendorPath = $basePath . '/' . $vendorName;

                    foreach (new \DirectoryIterator($vendorPath) as $module) {
                        if ($module->isDot() || !$module->isDir()) {
                            continue;
                        }
                        $moduleName = $module->getFilename();
                        $apiDir = $vendorPath . '/' . $moduleName . '/Api';

                        if (is_dir($apiDir)) {
                            $paths[] = $apiDir;
                            $namespaces["{$vendorName}\\{$moduleName}\\Api\\"] = $apiDir;
                        }
                    }
                }
            }
        }

        return self::$cache = ['paths' => $paths, 'namespaces' => $namespaces];
    }

    /**
     * Clear the discovery cache (for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
