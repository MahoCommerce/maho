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

use Maho\ComposerPlugin\AutoloadRuntime;

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

        // Use globPackages so that Api/ directories are found across all installed
        // packages (vendor/mahocommerce/maho, maho-modules, and the root project),
        // not just BP/app/code/ which misses core modules in Composer-based projects.
        foreach (AutoloadRuntime::globPackages('/app/code/*/*/*/Api', GLOB_ONLYDIR) as $apiDir) {
            $pos = strpos($apiDir, '/app/code/');
            if ($pos === false) {
                continue;
            }
            // Segments after app/code/: [pool, nsPrefix, moduleName, 'Api']
            $segments = explode('/', substr($apiDir, $pos + strlen('/app/code/')));
            if (count($segments) !== 4) {
                continue;
            }
            [, $nsPrefix, $moduleName] = $segments;

            $paths[] = $apiDir;
            $namespaces["{$nsPrefix}\\{$moduleName}\\Api\\"] = $apiDir;
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
