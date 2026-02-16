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
 * Scans each module's Api/Resource/ directory for ApiResource DTOs.
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

        $basePath = BP . '/app/code/core/Maho';
        $paths = [];
        $namespaces = [];

        if (!is_dir($basePath)) {
            return self::$cache = ['paths' => [], 'namespaces' => []];
        }

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
            $resourceDir = $apiDir . '/Resource';

            if (is_dir($resourceDir)) {
                $paths[] = $resourceDir;
                $namespaces["Maho\\{$moduleName}\\Api\\"] = $apiDir;
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
