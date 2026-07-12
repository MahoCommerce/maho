<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const WCAG_LEVELS = ['A', 'AA', 'AAA'];

    public function getDefaultWcagLevel(): string
    {
        return $this->normalizeWcagLevel(Mage::getStoreConfig('accessibilityscan/general/wcag_level'));
    }

    public function normalizeWcagLevel(?string $level): string
    {
        $level = strtoupper(trim((string) $level));
        return in_array($level, self::WCAG_LEVELS, true) ? $level : 'AA';
    }

    /**
     * Scan timeout in seconds
     */
    public function getScanTimeout(): int
    {
        return max(Mage::getStoreConfigAsInt('accessibilityscan/general/timeout'), 10);
    }

    public function getNodePath(): string
    {
        return trim((string) Mage::getStoreConfig('accessibilityscan/advanced/node_path')) ?: 'node';
    }

    public function getNpmPath(): string
    {
        return trim((string) Mage::getStoreConfig('accessibilityscan/advanced/npm_path')) ?: 'npm';
    }

    /**
     * Base working directory (var/accessibility-scan), created on demand
     */
    public function getBaseDir(): string
    {
        return $this->ensureDir(Mage::getBaseDir('var') . DS . 'accessibility-scan');
    }

    public function getPlaywrightDir(): string
    {
        return $this->ensureDir($this->getBaseDir() . DS . 'playwright');
    }

    public function getBrowsersDir(): string
    {
        return $this->getPlaywrightDir() . DS . 'browsers';
    }

    public function getScreenshotDir(): string
    {
        return $this->ensureDir($this->getBaseDir() . DS . 'screenshots');
    }

    /**
     * axe-core tags for a WCAG conformance level (levels are cumulative)
     *
     * @return list<string>
     */
    public function getWcagTags(string $level): array
    {
        $tags = ['wcag2a', 'wcag21a'];
        if ($level === 'AA' || $level === 'AAA') {
            $tags = [...$tags, 'wcag2aa', 'wcag21aa'];
        }
        if ($level === 'AAA') {
            $tags[] = 'wcag2aaa';
        }
        return $tags;
    }

    protected function ensureDir(string $dir): string
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Mage::throwException($this->__('Unable to create directory %s', $dir));
        }
        return $dir;
    }
}
