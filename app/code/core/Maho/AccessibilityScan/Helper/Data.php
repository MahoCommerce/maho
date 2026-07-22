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

    public const VIEWPORT_DESKTOP = 'desktop';
    public const VIEWPORT_MOBILE = 'mobile';

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
     * Age in days after which scans are deleted; 0 keeps everything
     */
    public function getCleanupDays(): int
    {
        return max(Mage::getStoreConfigAsInt('accessibilityscan/general/cleanup_days'), 0);
    }

    /**
     * Scan timeout in seconds
     */
    public function getScanTimeout(): int
    {
        return max(Mage::getStoreConfigAsInt('accessibilityscan/general/timeout'), 10);
    }

    /**
     * Viewports every scan runs, keyed by device name. Each page is scanned
     * once per viewport; the mobile pass also enables mobile emulation.
     *
     * @return array<string, array{width: int, height: int, mobile: bool}>
     */
    public function getViewports(): array
    {
        return [
            self::VIEWPORT_DESKTOP => $this->parseViewport('accessibilityscan/general/viewport_desktop', 1280, 1024, false),
            self::VIEWPORT_MOBILE => $this->parseViewport('accessibilityscan/general/viewport_mobile', 390, 844, true),
        ];
    }

    /**
     * Parse a "WIDTHxHEIGHT" config value, falling back to the given defaults
     *
     * @return array{width: int, height: int, mobile: bool}
     */
    protected function parseViewport(string $configPath, int $defaultWidth, int $defaultHeight, bool $mobile): array
    {
        if (preg_match('/^\s*(\d+)\s*[x×]\s*(\d+)\s*$/iu', (string) Mage::getStoreConfig($configPath), $m)
            && (int) $m[1] > 0 && (int) $m[2] > 0
        ) {
            return ['width' => (int) $m[1], 'height' => (int) $m[2], 'mobile' => $mobile];
        }
        return ['width' => $defaultWidth, 'height' => $defaultHeight, 'mobile' => $mobile];
    }

    /** Minimum Node.js major version required by Playwright */
    public const MIN_NODE_MAJOR = 20;

    public function getNodePath(): string
    {
        return trim((string) Mage::getStoreConfig('accessibilityscan/advanced/node_path')) ?: 'node';
    }

    public function getNpmPath(): string
    {
        return trim((string) Mage::getStoreConfig('accessibilityscan/advanced/npm_path')) ?: 'npm';
    }

    /**
     * Resolve a binary name or path to an absolute executable file, or null
     * when it cannot be found
     */
    public function resolveBinaryPath(string $binary): ?string
    {
        if (str_contains($binary, '/') || str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_file($binary) && is_executable($binary) ? $binary : null;
        }
        return Mage::findExecutable($binary);
    }

    /**
     * Installed Node.js version (e.g. "22.11.0"), or null when node is
     * missing or does not report a parsable version
     */
    public function getNodeVersion(): ?string
    {
        $node = $this->resolveBinaryPath($this->getNodePath());
        if ($node === null) {
            return null;
        }

        $process = proc_open([$node, '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return preg_match('/v?(\d+\.\d+\.\d+)/', $output, $m) ? $m[1] : null;
    }

    /**
     * Whether the Playwright package and browser have already been installed
     * by a previous scan
     */
    public function isPlaywrightInstalled(): bool
    {
        return is_file($this->getPlaywrightDir() . DS . 'node_modules' . DS . '.package-lock.json');
    }

    /**
     * Human-readable problems that will prevent the scanner from running,
     * empty when all requirements are met
     *
     * @return list<string>
     */
    public function getRequirementIssues(): array
    {
        $issues = [];

        if ($this->resolveBinaryPath($this->getNodePath()) === null) {
            $issues[] = $this->__(
                'Node.js was not found (looking for "%s"). Install Node.js %s or newer, or set its full path in System > Configuration > Accessibility Scan.',
                $this->getNodePath(),
                self::MIN_NODE_MAJOR,
            );
        } else {
            $version = $this->getNodeVersion();
            if ($version !== null && version_compare($version, self::MIN_NODE_MAJOR . '.0.0', '<')) {
                $issues[] = $this->__(
                    'Node.js %s is installed, but the scanner requires version %s or newer.',
                    $version,
                    self::MIN_NODE_MAJOR,
                );
            }
        }

        if ($this->resolveBinaryPath($this->getNpmPath()) === null) {
            $issues[] = $this->__(
                'npm was not found (looking for "%s"). Install it together with Node.js, or set its full path in System > Configuration > Accessibility Scan.',
                $this->getNpmPath(),
            );
        }

        return $issues;
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
     * axe-core tags for a WCAG conformance level (levels are cumulative,
     * covering WCAG 2.0 through 2.2). Tags without matching axe-core rules
     * are simply ignored by the scanner.
     *
     * @return list<string>
     */
    public function getWcagTags(string $level): array
    {
        $tags = ['wcag2a', 'wcag21a', 'wcag22a'];
        if ($level === 'AA' || $level === 'AAA') {
            $tags = [...$tags, 'wcag2aa', 'wcag21aa', 'wcag22aa'];
        }
        if ($level === 'AAA') {
            $tags = [...$tags, 'wcag2aaa', 'wcag21aaa', 'wcag22aaa'];
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
