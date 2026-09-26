<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;
use Maho\Storage\Mount;

class Maho_MediaCleaner_Helper_Data extends Mage_Core_Helper_Abstract
{
    /** A scan never lists a file below a folder with one of these names. */
    public const EXCLUDED_NAMES = ['cache', 'watermark', 'optimized', '.thumbs'];

    /** The directory on the media mount that holds the files of a scan type, or null for an unknown type. */
    public function getMountDirByType(string $type): ?string
    {
        return match ($type) {
            'category'      => 'catalog/category',
            'product'       => 'catalog/product',
            'product_cache' => 'catalog/product/cache',
            'wysiwyg'       => 'wysiwyg',
            default         => null,
        };
    }

    /**
     * The mount path of a scan result, or null when the type is unknown or the path leaves its directory.
     */
    public function getImageMountPath(Mount $mount, string $type, string $path): ?string
    {
        $directory = $this->getMountDirByType($type);
        if ($directory === null) {
            return null;
        }

        return \Maho\Io::getPathWithinMount($mount, $directory, $path);
    }

    /**
     * Every file below $directory on the mount, relative to $directory, in sorted order.
     *
     * @return list<string>
     * @throws FilesystemException
     */
    public function listAllFiles(Mount $mount, string $directory): array
    {
        $directory = trim($directory, '/');
        $prefix = $directory === '' ? '' : $directory . '/';
        $files = [];
        foreach ($mount->listContents($directory, true) as $item) {
            if ($item->isFile() && str_starts_with($item->path(), $prefix)) {
                $files[] = substr($item->path(), strlen($prefix));
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * The files below $directory on the mount that a scan examines, relative to $directory.
     *
     * @return list<string>
     * @throws FilesystemException
     */
    public function listFiles(Mount $mount, string $directory): array
    {
        $directory = trim($directory, '/');
        $blacklistedPatterns = $this->getBlacklistedPatterns();
        $files = [];
        $folders = [$directory];
        while (($folder = array_pop($folders)) !== null) {
            foreach ($mount->listContents($folder, false) as $item) {
                $file = ltrim(substr($item->path(), strlen($directory)), '/');
                if ($this->isExcluded($file, $directory, $blacklistedPatterns)) {
                    continue;
                }
                if ($item->isDir()) {
                    $folders[] = $item->path();
                } else {
                    $files[] = $file;
                }
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Remove the files that are below an excluded folder or that match a blacklist pattern.
     *
     * @param list<string> $files paths relative to $directory
     * @param list<string> $blacklistedPatterns
     * @return list<string>
     */
    public function filterFiles(array $files, string $directory, array $blacklistedPatterns): array
    {
        return array_values(array_filter(
            $files,
            fn(string $file): bool => !$this->isExcluded($file, $directory, $blacklistedPatterns),
        ));
    }

    /**
     * True when a folder or the file in $file has an excluded name, or matches a blacklist pattern.
     * The patterns apply to the path relative to the media mount, like "wysiwyg/images2021".
     *
     * @param list<string> $blacklistedPatterns
     */
    public function isExcluded(string $file, string $directory, array $blacklistedPatterns): bool
    {
        $directory = trim($directory, '/');
        $path = $directory === '' ? '' : '/' . $directory;
        foreach (explode('/', $file) as $segment) {
            if (in_array($segment, self::EXCLUDED_NAMES, true)) {
                return true;
            }
            $path .= '/' . $segment;
            if ($this->isBlacklisted($path, $blacklistedPatterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The files of the product image cache whose source image is gone, relative to catalog/product/cache.
     * One deep listing of catalog/product gives both the cache files and the source files.
     *
     * @return list<string>
     * @throws FilesystemException
     */
    public function findUnusedProductCacheFiles(Mount $mount, string $extension): array
    {
        $cacheFiles = [];
        $sourceFiles = [];
        foreach ($this->listAllFiles($mount, 'catalog/product') as $file) {
            if (str_starts_with($file, 'cache/')) {
                $cacheFiles[] = substr($file, strlen('cache/'));
            } else {
                $sourceFiles[$file] = true;
            }
        }

        $cacheFiles = $this->filterFiles($cacheFiles, 'catalog/product/cache', $this->getBlacklistedPatterns());

        return $this->getUnusedProductCacheFiles($cacheFiles, $sourceFiles, $extension);
    }

    /**
     * A cache file is "{store}/{subdir}/[{WxH}/]{hash}/{a}/{b}/{name}{extension}". Its source is
     * "{a}/{b}/{name}" below catalog/product. A cache file is unused when that source is not in
     * $sourceFiles, or when its name does not end with the configured $extension.
     *
     * @param list<string> $cacheFiles paths relative to catalog/product/cache
     * @param array<string, true> $sourceFiles paths relative to catalog/product
     * @return list<string>
     */
    public function getUnusedProductCacheFiles(array $cacheFiles, array $sourceFiles, string $extension): array
    {
        $unusedFiles = [];
        foreach ($cacheFiles as $cacheFile) {
            if (str_contains($cacheFile, '/placeholder/')) {
                continue;
            }

            $source = implode('/', array_slice(explode('/', $cacheFile), -3));
            if (!str_ends_with($source, $extension)
                || !isset($sourceFiles[substr($source, 0, -strlen($extension))])
            ) {
                $unusedFiles[] = $cacheFile;
            }
        }

        return $unusedFiles;
    }

    /**
     * The files in $files that no content in $contents names. $files are relative to the wysiwyg
     * directory, and a content names a file with its path below the media URL, like "wysiwyg/a.jpg".
     *
     * @param list<string> $files
     * @param list<string> $contents
     * @return list<string>
     */
    public function getUnusedWysiwygFiles(array $files, array $contents, bool $swatchesEnabled): array
    {
        $unusedFiles = [];
        foreach ($files as $file) {
            $mediaPath = 'wysiwyg/' . $file;
            if ($swatchesEnabled && fnmatch('wysiwyg/swatches/*', $mediaPath)) {
                continue;
            }
            if (array_any($contents, fn(string $content): bool => stripos($content, $mediaPath) !== false)) {
                continue;
            }
            $unusedFiles[] = $file;
        }

        return $unusedFiles;
    }

    /**
     * Delete the file of a scan result. A file that is already gone counts as deleted.
     * A path that leaves its directory is never touched on the mount and counts as deleted too,
     * because it cannot name a file of the scan.
     */
    public function deleteImageFile(Mount $mount, string $type, string $path): bool
    {
        $file = $this->getImageMountPath($mount, $type, $path);
        if ($file === null) {
            return true;
        }

        try {
            $mount->delete($file);
        } catch (FilesystemException $e) {
            Mage::logException($e);
            return false;
        }

        return true;
    }

    /**
     * Delete everything below $directory on the mount and keep the directory. An empty $directory
     * is the mount root. Returns false when a new listing still finds a file.
     */
    public function flushDirectory(Mount $mount, string $directory): bool
    {
        try {
            foreach ($mount->listContents($directory, false)->toArray() as $item) {
                if ($item->isDir()) {
                    $mount->deleteDirectory($item->path());
                } else {
                    $mount->delete($item->path());
                }
            }
        } catch (FilesystemException $e) {
            Mage::logException($e);
        }

        try {
            return $this->listAllFiles($mount, $directory) === [];
        } catch (FilesystemException $e) {
            Mage::logException($e);
            return false;
        }
    }

    public function getAllCSSFilesContents(): array
    {
        $files = $this->getAllCSSFiles(Mage::getBaseDir('skin') . '/frontend');
        foreach ($files as $k => $cssFilePath) {
            $files[$k] = file_get_contents($cssFilePath);
        }

        return $files;
    }

    public function getAllCSSFiles(string $dir): array
    {
        $result = [];
        $root = scandir($dir);
        foreach ($root as $value) {
            if ($value === '.' || $value === '..') {
                continue;
            }
            if (str_ends_with($value, '.css') && is_file("$dir/$value")) {
                $result[] = "$dir/$value";
                continue;
            }

            if (is_dir("$dir/$value")) {
                foreach ($this->getAllCSSFiles("$dir/$value") as $file) {
                    $result[] = $file;
                }
            }
        }
        return $result;
    }

    /**
     * @return list<string>
     */
    protected function getBlacklistedPatterns(): array
    {
        $blacklist = Mage::getStoreConfig('admin/mediacleaner/blacklist');
        if ($blacklist === null) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $blacklist)),
            fn(string $pattern): bool => $pattern !== '',
        ));
    }

    public function isBlacklisted(string $path, array $blacklistedPatterns): bool
    {
        return array_any($blacklistedPatterns, fn($blacklistedPattern) => fnmatch('*/' . $blacklistedPattern, $path));
    }
}
