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
     * The scanner fetches the URL server-side with a headless browser, so
     * arbitrary targets would allow SSRF into the internal network. Only
     * allow URLs whose host and port match a configured store base URL.
     */
    public function isAllowedScanUrl(string $url): bool
    {
        return $this->resolveScanUrlStoreId($url) !== null;
    }

    /**
     * Id of the first store whose base URL authority matches the given URL,
     * or null when the URL does not belong to any configured store.
     * Frontend stores are checked before the admin store, so a frontend
     * store id wins when both share the same base URL.
     */
    public function resolveScanUrlStoreId(string $url): ?int
    {
        $authority = $this->getUrlAuthority($url);
        if ($authority === null) {
            return null;
        }

        $stores = Mage::app()->getStores();
        $stores[] = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        foreach ($stores as $store) {
            foreach ([false, true] as $secure) {
                // getBaseUrl() resolves {{base_url}}-style placeholders that the
                // raw store config may still contain on default installs
                $baseAuthority = $this->getUrlAuthority($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, $secure));
                if ($baseAuthority !== null && $baseAuthority === $authority) {
                    return (int) $store->getId();
                }
            }
        }
        return null;
    }

    /**
     * Normalized "host:port" for an http(s) URL (default port filled in from
     * the scheme), or null when the URL is not a valid absolute http(s) URL.
     */
    protected function getUrlAuthority(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        return $host . ':' . $port;
    }

    public function isScheduledScanEnabled(): bool
    {
        return Mage::getStoreConfigFlag('accessibilityscan/scheduled/enabled');
    }

    /**
     * Scheduled-scan URLs (one per line in admin), trimmed and de-duplicated
     *
     * @return list<string>
     */
    public function getScheduledScanUrls(): array
    {
        $urls = [];
        foreach (preg_split('/\R/', (string) Mage::getStoreConfig('accessibilityscan/scheduled/urls')) ?: [] as $line) {
            $url = trim($line);
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * How many scheduled scans to keep per URL; 0 disables pruning
     */
    public function getScheduledScanRetention(): int
    {
        return max(Mage::getStoreConfigAsInt('accessibilityscan/scheduled/retention'), 0);
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
