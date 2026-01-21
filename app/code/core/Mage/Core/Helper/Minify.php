<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use MatthiasMullie\Minify\CSS as CSSMinifier;
use MatthiasMullie\Minify\JS as JSMinifier;

class Mage_Core_Helper_Minify extends Mage_Core_Helper_Abstract
{
    private const CACHE_DIR = 'public/media/mahominify';

    /**
     * Runtime cache to avoid processing the same file multiple times
     * Static so all instances share the same cache
     */
    private static array $processedCssFiles = [];
    private static array $processedJsFiles = [];

    /**
     * Check if CSS minification is enabled
     */
    public function isCssMinificationEnabled(): bool
    {
        return Mage::getStoreConfigFlag('dev/css/minify_enabled');
    }

    /**
     * Check if JS minification is enabled
     */
    public function isJsMinificationEnabled(): bool
    {
        return Mage::getStoreConfigFlag('dev/js/minify_enabled');
    }

    /**
     * Minify CSS file and return minified file URL
     */
    public function minifyCss(string $filePath): string
    {
        if (!$this->isCssMinificationEnabled()) {
            $this->sendEarlyHint($filePath, 'style');
            return $filePath;
        }

        // Don't minify external URLs, but still send Early Hint
        if ($this->isExternalUrl($filePath)) {
            $this->sendEarlyHint($filePath, 'style');
            return $filePath;
        }

        // Check runtime cache first
        if (isset(self::$processedCssFiles[$filePath])) {
            $this->sendEarlyHint(self::$processedCssFiles[$filePath], 'style');
            return self::$processedCssFiles[$filePath];
        }

        $result = $this->processFile($filePath, 'css');
        self::$processedCssFiles[$filePath] = $result;

        $this->sendEarlyHint($result, 'style');
        return $result;
    }

    /**
     * Minify JS file and return minified file URL
     */
    public function minifyJs(string $filePath): string
    {
        if (!$this->isJsMinificationEnabled()) {
            $this->sendEarlyHint($filePath, 'script');
            return $filePath;
        }

        // Don't minify external URLs, but still send Early Hint
        if ($this->isExternalUrl($filePath)) {
            $this->sendEarlyHint($filePath, 'script');
            return $filePath;
        }

        // Check runtime cache first
        if (isset(self::$processedJsFiles[$filePath])) {
            $this->sendEarlyHint(self::$processedJsFiles[$filePath], 'script');
            return self::$processedJsFiles[$filePath];
        }

        $result = $this->processFile($filePath, 'js');
        self::$processedJsFiles[$filePath] = $result;

        $this->sendEarlyHint($result, 'script');
        return $result;
    }

    /**
     * Check if file is already minified based on filename patterns
     */
    private function isAlreadyMinified(string $filePath): bool
    {
        return (bool) preg_match('/[._-](min|pack)\./i', $filePath);
    }

    /**
     * Check if the given path is an external URL (different domain)
     */
    private function isExternalUrl(string $filePath): bool
    {
        // If it doesn't look like a URL, it's not external
        if (!str_starts_with($filePath, 'http://') && !str_starts_with($filePath, 'https://') && !str_starts_with($filePath, '//')) {
            return false;
        }

        // Get the current site's base URL
        $baseUrl = Mage::getBaseUrl();
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);

        // Parse the file URL
        $fileDomain = parse_url($filePath, PHP_URL_HOST);

        // If domains don't match, it's external
        return $fileDomain !== $baseDomain;
    }

    /**
     * Process file minification with caching
     */
    private function processFile(string $filePath, string $type): string
    {
        $absolutePath = $this->getAbsoluteFilePath($filePath);

        if (!file_exists($absolutePath)) {
            return $filePath; // Return original if file doesn't exist
        }

        $cachedFilename = $this->generateCachedFilename($absolutePath, $type);
        $cachedFile = $this->getCachedFilePath($cachedFilename);
        $cachedUrl = $this->getCachedFileUrl($cachedFilename);

        // Check if cached version exists and is valid
        if ($this->isCacheValid($absolutePath, $cachedFile)) {
            return $cachedUrl;
        }

        // Minify and cache with file locking to prevent race conditions
        try {
            $this->ensureCacheDirectory($type);

            $lockFile = $cachedFile . '.lock';
            $lockHandle = fopen($lockFile, 'c');

            if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
                // If we can't get a lock, return original file (another process is minifying)
                if ($lockHandle) {
                    fclose($lockHandle);
                }
                return $filePath;
            }

            try {
                if ($this->isAlreadyMinified($filePath)) {
                    // Copy already-minified files to maintain consistent URL/caching
                    copy($absolutePath, $cachedFile);
                } else {
                    $minifiedContent = $this->minifyContent(file_get_contents($absolutePath), $type);
                    file_put_contents($cachedFile, $minifiedContent);
                }
                return $cachedUrl;
            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($lockFile);
            }

        } catch (Exception $e) {
            Mage::logException($e);
            return $filePath; // Return original on error
        }
    }

    /**
     * Generate filename based on original name and modification time
     */
    private function generateCachedFilename(string $filePath, string $type): string
    {
        $originalName = pathinfo($filePath, PATHINFO_FILENAME);
        $mtime = filemtime($filePath);
        $extension = $type === 'css' ? 'css' : 'js';

        return $originalName . '-' . $mtime . '.' . $extension;
    }

    /**
     * Check if cached file is valid (exists and newer than source)
     */
    private function isCacheValid(string $sourceFile, string $cachedFile): bool
    {
        return file_exists($cachedFile) &&
               filemtime($cachedFile) >= filemtime($sourceFile);
    }

    /**
     * Minify content based on type
     */
    private function minifyContent(string $content, string $type): string
    {
        return match ($type) {
            'css' => (new CSSMinifier($content))->minify(),
            'js' => (new JSMinifier($content))->minify(),
            default => $content,
        };
    }

    /**
     * Get absolute file path from relative path or URL
     */
    private function getAbsoluteFilePath(string $filePath): string
    {
        // Handle URLs by converting them to file paths
        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
            // Extract path from URL
            $urlParts = parse_url($filePath);
            $path = $urlParts['path'] ?? '';

            // Remove any query string or fragment from the path
            $path = strtok($path, '?') ?: $path;
            $path = strtok($path, '#') ?: $path;

            return Mage::getBaseDir() . '/public' . $path;
        }

        // Handle relative paths - assume they're from public directory
        $cleanPath = ltrim($filePath, '/');
        return Mage::getBaseDir() . '/public/' . $cleanPath;
    }

    /**
     * Get cached file path
     */
    private function getCachedFilePath(string $filename): string
    {
        return Mage::getBaseDir() . '/' . self::CACHE_DIR . '/' . $filename;
    }

    /**
     * Get cached file URL
     */
    private function getCachedFileUrl(string $filename): string
    {
        return Mage::getBaseUrl('media') . 'mahominify/' . $filename;
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(string $type): void
    {
        $dir = Mage::getBaseDir() . '/' . self::CACHE_DIR;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Clear minification cache
     */
    public function clearCache(): void
    {
        $cacheDir = Mage::getBaseDir() . '/' . self::CACHE_DIR;

        if (!is_dir($cacheDir)) {
            return;
        }

        // Clear all CSS and JS files
        $patterns = ['*.css', '*.js'];
        foreach ($patterns as $pattern) {
            $files = glob($cacheDir . '/' . $pattern);
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // Also clear the runtime cache
        self::$processedCssFiles = [];
        self::$processedJsFiles = [];
    }

    /**
     * Send preload hint with Early Hints support for FrankenPHP
     */
    private function sendEarlyHint(string $url, string $as): void
    {
        // Only send preload hints on frontend and if headers haven't been sent
        if (Mage::app()->getStore()->isAdmin() || headers_sent()) {
            return;
        }

        // Send Link header for preload
        header("Link: <{$url}>; rel=preload; as={$as}", false);

        // If running under FrankenPHP, send Early Hints
        if (function_exists('headers_send')) {
            headers_send(103);
        }
    }

    /**
     * Clean up old versions of minified files (cron job method)
     * Removes files older than 7 days to keep recent versions for rollback
     */
    public function cleanupOldVersions(): void
    {
        $cacheDir = Mage::getBaseDir() . '/' . self::CACHE_DIR;

        if (!is_dir($cacheDir)) {
            return;
        }

        $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 days ago
        $extensions = ['css', 'js'];

        foreach ($extensions as $extension) {
            $pattern = '*-*.' . $extension;
            $files = glob($cacheDir . '/' . $pattern);

            foreach ($files as $file) {
                $filename = basename($file);
                // Extract mtime from filename: originalname-mtime.ext
                if (preg_match('/^(.+)-(\d+)\.' . $extension . '$/', $filename, $matches)) {
                    $fileMtime = (int) $matches[2];
                    // Remove if older than cutoff time
                    if ($fileMtime < $cutoffTime) {
                        unlink($file);
                        Mage::log("Cleaned up old minified file: {$filename}", Mage::LOG_INFO);
                    }
                }
            }
        }
    }

}
