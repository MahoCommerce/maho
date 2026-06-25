<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function scandirRecursive(string $dir): array
    {
        $result = [];
        $blacklistPatterns = $this->getBlacklistedPatterns();
        $root = scandir($dir);
        foreach ($root as $value) {
            if (in_array($value, ['.', '..', 'cache', 'watermark', 'optimized', '.thumbs'], true)) {
                continue;
            }
            if ($this->isBlacklisted("$dir/$value", $blacklistPatterns)) {
                continue;
            }
            if (is_file("$dir/$value")) {
                $result[] = "$dir/$value";
                continue;
            }

            if (is_dir("$dir/$value")) {
                foreach ($this->scandirRecursive("$dir/$value") as $file) {
                    $result[] = $file;
                }
            }
        }
        return $result;
    }

    public function getMediaDirByType(string $type): string
    {
        return match ($type) {
            'category'      => Mage::getBaseDir('media') . '/catalog/category/',
            'product'       => Mage::getBaseDir('media') . '/catalog/product/',
            'product_cache' => Mage::getBaseDir('media') . '/catalog/product/cache/',
            'wysiwyg'       => Mage::getBaseDir('media') . '/wysiwyg/',
            default         => Mage::getBaseDir('media') . '/',
        };
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

    protected function getBlacklistedPatterns(): array
    {
        $blacklist = Mage::getStoreConfig('admin/mediacleaner/blacklist');
        if ($blacklist === null) {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $blacklist);
    }

    public function isBlacklisted(string $path, array $blacklistedPatterns): bool
    {
        return array_any($blacklistedPatterns, fn($blacklistedPattern) => fnmatch('*/' . $blacklistedPattern, $path));
    }
}
