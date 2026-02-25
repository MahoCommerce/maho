<?php

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
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
        usort($paths, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($paths as $installPath) {
            if (str_starts_with($path, $installPath)) {
                $path = substr($path, strlen($installPath));
                break;
            }
        }

        return ltrim($path, '/');
    }

    /**
     * Return a list of all files matching the pattern from installed packages
     */
    public static function globPackages(string $pattern, int $flags = 0): array
    {
        $pattern = self::toRelativePath($pattern);
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
        $results = [];
        foreach (self::globPackages("$path/*", GLOB_ONLYDIR) as $dir) {
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
    public static function findClassFile(string $class): string|false
    {
        return realpath(self::getComposerAutoloader()->findFile($class));
    }

    /**
     * Display the maintenance page and exit
     *
     * Looks for templates using Maho's fallback system:
     * 1. app/design/maintenance/{store_code}.phtml (if MAGE_RUN_CODE is set)
     * 2. app/design/maintenance/default.phtml
     */
    public static function maintenancePage(): never
    {
        header('HTTP/1.1 503 Service Unavailable', true, 503);
        header('Retry-After: 3600');
        header('X-Robots-Tag: noindex');
        header('Content-Type: text/html; charset=UTF-8');

        $template = self::findMaintenanceTemplate();

        if ($template !== false) {
            include $template;
        }

        exit;
    }

    /**
     * Find the maintenance template file using Maho's fallback system
     */
    private static function findMaintenanceTemplate(): string|false
    {
        $runCode = $_SERVER['MAGE_RUN_CODE'] ?? '';

        if ($runCode !== '') {
            $storeTemplate = self::findFile("app/design/maintenance/{$runCode}.phtml");
            if ($storeTemplate !== false) {
                return $storeTemplate;
            }
        }

        return self::findFile('app/design/maintenance/default.phtml');
    }

    /**
     * Generate an error report and output HTML
     */
    public static function errorReport(array $reportData = [], int $httpResponseCode = 503): void
    {
        $reportIdMessage = '';
        if ($reportData) {
            $reportId   = abs((int) (microtime(true) * random_int(100, 1000)));
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

    public static function getImageManager(array $customOptions = []): \Intervention\Image\ImageManager
    {
        $defaultOptions = [
            'autoOrientation' => false,
            'strip' => true,
        ];
        $options = [
            ...$defaultOptions,
            ...$customOptions,
        ];

        $driverClasses = [
            \Intervention\Image\Drivers\Gd\Driver::class,
        ];

        if (\Composer\InstalledVersions::isInstalled('intervention/image-driver-vips')) {
            // @phpstan-ignore class.notFound
            array_unshift($driverClasses, \Intervention\Image\Drivers\Vips\Driver::class);
        }

        foreach ($driverClasses as $driverClass) {
            try {
                return \Intervention\Image\ImageManager::withDriver($driverClass, ...$options);
            } catch (Intervention\Image\Exceptions\DriverException) {
            }
        }

        Mage::throwException('No image driver found');
    }

    /**
     * Sign image transformation parameters into a query string: "t=...&s=..."
     */
    public static function signImageResizeRequest(array $params, string $key): string
    {
        $t = base64_encode(json_encode($params, JSON_THROW_ON_ERROR));
        $s = hash_hmac('sha256', $t, $key);
        return 't=' . urlencode($t) . '&s=' . $s;
    }

    /**
     * Verify a signed image resize request and return decoded parameters, or null on failure.
     */
    public static function verifyImageResizeRequest(string $t, string $s, string $key): ?array
    {
        $expected = hash_hmac('sha256', $t, $key);
        if (!hash_equals($expected, $s)) {
            return null;
        }

        $decoded = base64_decode($t, true);
        if ($decoded === false) {
            return null;
        }

        $params = json_decode($decoded, true);
        if (!is_array($params)) {
            return null;
        }

        return $params;
    }

    /**
     * Build the cache file path for a resized product image from transform params.
     * Single source of truth used by both Mage_Catalog_Model_Product_Image::setBaseFile()
     * and image.php to ensure consistent cache paths.
     */
    public static function buildImageResizeCachePath(array $params, string $baseMediaPath, string $sourceFile): string
    {
        $storeId = (int) \Mage::app()->getStore()->getId();
        $path = [$baseMediaPath, 'cache', $storeId, $params['_destinationSubdir']];

        if (!empty($params['_width']) || !empty($params['_height'])) {
            $path[] = "{$params['_width']}x{$params['_height']}";
        }

        $miscParams = [
            ($params['_keepAspectRatio'] ? '' : 'non') . 'proportional',
            ($params['_keepFrame'] ? '' : 'no') . 'frame',
            ($params['_keepTransparency'] ? '' : 'no') . 'transparency',
            ($params['_constrainOnly'] ? 'do' : 'not') . 'constrainonly',
            $params['_backgroundColorStr'],
            'angle' . $params['_angle'],
            'quality' . $params['_quality'],
        ];

        if (isset($params['_watermarkFile'])) {
            $miscParams[] = $params['_watermarkFile'];
            $miscParams[] = $params['_watermarkImageOpacity'];
            $miscParams[] = $params['_watermarkPosition'];
            $miscParams[] = $params['_watermarkWidth'];
            $miscParams[] = $params['_watermarkHeigth'];
        }

        $path[] = md5(implode('_', $miscParams));

        $targetExt = image_type_to_extension((int) \Mage::getStoreConfig('system/media_storage_configuration/image_file_type'));
        $file = preg_replace('/\.[^.]+$/', $targetExt, $sourceFile);

        return implode('/', $path) . $file;
    }
}
