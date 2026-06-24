<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

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
    private const CACHE_ID = 'maho_api_platform_discovery';
    // Part of the "api_data" cache type (see etc/config.xml), so flushing the
    // API Data cache in admin clears the module discovery map too.
    private const CACHE_TAG = 'API_DISCOVERY';

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

        // Kernel::boot() runs on every request, so the directory glob below would
        // re-scan the filesystem on the hot path. Persist the result through the
        // Maho cache (cleared by cache:flush) so subsequent requests skip the scan.
        $cached = self::loadFromCache();
        if ($cached !== null) {
            return self::$cache = $cached;
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

        self::$cache = ['paths' => $paths, 'namespaces' => $namespaces];
        self::saveToCache(self::$cache);

        return self::$cache;
    }

    /**
     * Clear the discovery cache (for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = null;

        $app = self::mageApp();
        $app?->removeCache(self::CACHE_ID);
    }

    /**
     * @return array{paths: string[], namespaces: array<string, string>}|null
     */
    private static function loadFromCache(): ?array
    {
        $app = self::mageApp();
        if ($app === null) {
            return null;
        }

        $raw = $app->loadCache(self::CACHE_ID);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = \Mage::helper('core')->jsonDecode($raw);
        } catch (\Throwable) {
            return null;
        }
        if (is_array($decoded) && isset($decoded['paths'], $decoded['namespaces'])) {
            /** @var array{paths: string[], namespaces: array<string, string>} $decoded */
            return $decoded;
        }

        return null;
    }

    /**
     * @param array{paths: string[], namespaces: array<string, string>} $data
     */
    private static function saveToCache(array $data): void
    {
        $app = self::mageApp();
        $app?->saveCache(\Mage::helper('core')->jsonEncode($data), self::CACHE_ID, [self::CACHE_TAG]);
    }

    /**
     * Returns the Mage app only when it's safe to touch the cache subsystem.
     * Falls back to null (uncached glob) for CLI/warmup contexts where Mage
     * isn't initialised, so discovery never hard-depends on a booted app.
     */
    private static function mageApp(): ?\Mage_Core_Model_App
    {
        if (!class_exists(\Mage::class) || !\Mage::isInstalled()) {
            return null;
        }

        try {
            return \Mage::app();
        } catch (\Throwable) {
            return null;
        }
    }
}
