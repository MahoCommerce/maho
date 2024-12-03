<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    Maho
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\ComposerPlugin\AutoloadRuntime;
use Composer\Autoload\ClassLoader;

/**
 * Maho utility class
 *
 * @phpstan-import-type PackageArray from AutoloadRuntime
 */
final class Maho
{
    private static ?ClassLoader $composerClassLoader = null;

    private static ?string $bp = null;

    /**
     * Return an array of Maho packages installed by Composer
     *
     * @return PackageArray
     */
    public static function getInstalledPackages(): array
    {
        return AutoloadRuntime::getInstalledPackages();
    }

    /**
     * Return the install path of the root composer package
     */
    public static function getBasePath(): string
    {
        if (self::$bp === null) {
            self::$bp = realpath(self::getInstalledPackages()['root']['path']);
        }
        return self::$bp;
    }

    /**
     * Convert an absolute path to one relative to the base path
     */
    public static function toRelativePath(string $path): string
    {
        $paths = array_column(self::getInstalledPackages(), 'path');
        usort($paths, fn ($a, $b) => strlen($b) <=> strlen($a));
        return ltrim(str_replace($paths, '', $path), '/');
    }

    /**
     * Return a list of all files matching the pattern from installed packages
     */
    public static function globPackages(string $pattern, int $flags = 0): array
    {
        return AutoloadRuntime::globPackages($pattern, $flags);
    }

    /**
     * Return the absolute path of a file from installed packages respecting overrides
     */
    public static function findFile(string $path): string|false
    {
        $relativePath = self::toRelativePath($path);
        $paths = array_reverse(array_column(self::getInstalledPackages(), 'path'));

        // Temporarily set include paths, then revert
        $oldPaths = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, $paths));
        $file = stream_resolve_include_path($relativePath);
        set_include_path($oldPaths);

        return $file;
    }

    /**
     * Return a list of all subdirectories from installed packages
     */
    public static function listDirectories(string $path): array
    {
        $relativePath = self::toRelativePath($path);
        $results = [];

        foreach (AutoloadRuntime::globPackages("$relativePath/*", GLOB_ONLYDIR) as $dir) {
            $results[] = basename($dir);
        }

        return array_unique($results);
    }

    /**
     * Return Composer's ClassLoader instance
     */
    public static function getComposerAutoloader(): ClassLoader
    {
        if (self::$composerClassLoader === null) {
            self::$composerClassLoader = require self::getBasePath() . '/vendor/autoload.php';
        }
        return self::$composerClassLoader;
    }

    /**
     * Update Composer's autoloader during development in case new files are added
     */
    public static function updateComposerAutoloader(): void
    {
        $composerClassLoader = self::getComposerAutoloader();

        $includePaths = AutoloadRuntime::generateIncludePaths();
        $includePaths[] = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, $includePaths));

        $composerClassLoader->addClassMap(AutoloadRuntime::generateClassMap());

        foreach (AutoloadRuntime::generatePsr0() as $prefix => $paths) {
            $composerClassLoader->add($prefix, $paths, true);
        }

        $requireFile = \Closure::bind(static function ($file) {
            require_once $file;
        }, null, null);

        foreach (AutoloadRuntime::generateIncludeFiles() as $file) {
            $requireFile($file);
        }
    }

    /**
     * Check if Composer was run with the `--optimize` flag
     */
    public static function isComposerAutoloaderOptimized(): bool
    {
        return isset(self::getComposerAutoloader()->getClassMap()['Mage_Core_Model_App']);
    }

    /**
     * Return the absolute path to a class file
     */
    public static function findClassFile($class): string|false
    {
        return realpath(self::getComposerAutoloader()->findFile($class));
    }

    /**
     * Generate an error report and output HTML
     */
    public static function errorReport(array $reportData = [], int $httpResponseCode = 503): void
    {
        $reportIdMessage = '';
        if ($reportData) {
            $reportId   = abs((int)(microtime(true) * random_int(100, 1000)));
            $reportIdMessage = "<p>Error log record number: {$reportId}</p>";
            $reportDir = Mage::getBaseDir('var') . '/report';
            if (!file_exists($reportDir)) {
                @mkdir($reportDir, 0750, true);
            }

            $reportFile = "{$reportDir}/$reportId";
            $reportData = array_map('strip_tags', $reportData);
            @file_put_contents($reportFile, serialize($reportData));
            @chmod($reportFile, 0640);
        }

        $description = match ($httpResponseCode) {
            404 => 'Not Found',
            503 => 'Service Unavailable',
            default => '',
        };
        header("HTTP/1.1 {$httpResponseCode} {$description}", true, $httpResponseCode);
        echo "<html><body><h1>There has been an error processing your request</h1>{$reportIdMessage}</body></html>";
    }
}
