<?php

/**
 * Shared Playwright runtime: one configurable Node.js package directory and
 * browser cache for every browser-based scanner.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Browser;

use Mage;

final class Runtime
{
    /** Minimum Node.js major version required by Playwright */
    public const MIN_NODE_MAJOR = 20;

    public const RUNTIME_DIR_ENV = 'MAHO_BROWSER_RUNTIME_DIR';
    public const BROWSERS_PATH_ENV = 'PLAYWRIGHT_BROWSERS_PATH';

    public const INSTALL_LOCK = 'browser_runtime_install';

    /** Seconds allowed for npm install / browser download */
    public const INSTALL_TIMEOUT = 900;

    private const MANIFEST_NAME = 'maho-browser-runtime';

    /** @var string|false|null false once probed with no result */
    private static string|false|null $globalNodeModules = null;

    private static ?string $playwrightVersion = null;

    /**
     * Exact Playwright version every store runs: the one package-lock.json
     * pins for the test suite, so the Chromium build does not depend on the
     * install date and a dependency bump updates scanners too
     */
    public static function getPlaywrightVersion(): string
    {
        if (self::$playwrightVersion === null) {
            // The lock file ships inside the Maho package, not at the store root
            $file = dirname(__DIR__, 3) . DS . 'package-lock.json';
            $version = null;
            if (is_file($file)) {
                try {
                    $lock = Mage::helper('core')->jsonDecode((string) file_get_contents($file));
                    $version = $lock['packages']['node_modules/playwright']['version'] ?? null;
                } catch (\JsonException) {
                    // reported below
                }
            }
            if (!is_string($version) || $version === '') {
                Mage::throwException(Mage::helper('core')->__('Unable to read the Playwright version from %s', $file));
            }
            self::$playwrightVersion = $version;
        }
        return self::$playwrightVersion;
    }

    /**
     * Scanners registered under global/browser/scanners, keyed by node name
     *
     * @return array<string, ScannerInterface>
     */
    public static function scanners(): array
    {
        $scanners = [];
        $node = Mage::getConfig()->getNode('global/browser/scanners');
        if ($node) {
            foreach ($node->children() as $name => $alias) {
                $model = Mage::getModel((string) $alias);
                if ($model instanceof ScannerInterface) {
                    $scanners[(string) $name] = $model;
                }
            }
        }
        return $scanners;
    }

    /**
     * Directory holding package.json and node_modules. The environment wins
     * over the store config, which wins over var/browser-runtime.
     */
    public function getRuntimeDir(): string
    {
        $dir = trim((string) getenv(self::RUNTIME_DIR_ENV));
        if ($dir === '') {
            $dir = trim((string) Mage::getStoreConfig('system/browser/runtime_dir'));
        }
        if ($dir === '') {
            $dir = Mage::getBaseDir('var') . DS . 'browser-runtime';
        }
        return $this->ensureDir($dir);
    }

    /**
     * Where Playwright downloads browsers to. PLAYWRIGHT_BROWSERS_PATH from the
     * environment is honoured as-is so an image can ship a prebuilt cache.
     */
    public function getBrowsersDir(): string
    {
        $dir = trim((string) getenv(self::BROWSERS_PATH_ENV));
        return $dir !== '' && $dir !== '0' ? $dir : $this->getRuntimeDir() . DS . 'browsers';
    }

    public function getNodePath(): string
    {
        return trim((string) Mage::getStoreConfig('system/browser/node_path')) ?: 'node';
    }

    public function getNpmPath(): string
    {
        return trim((string) Mage::getStoreConfig('system/browser/npm_path')) ?: 'npm';
    }

    /**
     * Installed Node.js version (e.g. "22.11.0"), or null when node is
     * missing or does not report a parsable version
     */
    public function getNodeVersion(): ?string
    {
        $node = Mage::findExecutable($this->getNodePath());
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
     * Human-readable problems that will prevent any scanner from running,
     * empty when all requirements are met
     *
     * @return list<string>
     */
    public function getRequirementIssues(): array
    {
        $helper = Mage::helper('core');
        $issues = [];

        if (Mage::findExecutable($this->getNodePath()) === null) {
            $issues[] = $helper->__(
                'Node.js was not found (looking for "%s"). Install Node.js %s or newer, or set its full path in System > Configuration > System > Browser Runtime.',
                $this->getNodePath(),
                self::MIN_NODE_MAJOR,
            );
        } else {
            $version = $this->getNodeVersion();
            if ($version !== null && version_compare($version, self::MIN_NODE_MAJOR . '.0.0', '<')) {
                $issues[] = $helper->__(
                    'Node.js %s is installed, but the browser runtime requires version %s or newer.',
                    $version,
                    self::MIN_NODE_MAJOR,
                );
            }
        }

        if (Mage::findExecutable($this->getNpmPath()) === null) {
            $issues[] = $helper->__(
                'npm was not found (looking for "%s"). Install it together with Node.js, or set its full path in System > Configuration > System > Browser Runtime.',
                $this->getNpmPath(),
            );
        }

        return $issues;
    }

    /**
     * Whether the pinned Playwright package, the given scanner packages and
     * the browser build are all present. Content-based, so a runtime a host
     * or an image provides counts as installed.
     *
     * @param array<string, string> $packages
     */
    public function isInstalled(Browser $browser, array $packages = []): bool
    {
        $modules = $this->getRuntimeDir() . DS . 'node_modules';
        if ($this->readPackageVersion($modules . DS . 'playwright') !== self::getPlaywrightVersion()) {
            return false;
        }
        foreach (array_keys($packages) as $package) {
            if (!is_dir($modules . DS . $package)) {
                return false;
            }
        }
        return $this->isBrowserInstalled($browser);
    }

    public function isBrowserInstalled(Browser $browser): bool
    {
        $revision = $this->getBrowserRevision($browser);
        return $revision !== null
            && is_dir($this->getBrowsersDir() . DS . $browser->directoryPrefix() . '-' . $revision);
    }

    /**
     * Build revision the installed Playwright package expects for a browser,
     * read from playwright-core's browsers.json next to the package's real
     * location (so a linked external package reports its own build)
     */
    public function getBrowserRevision(Browser $browser): ?string
    {
        $package = realpath($this->getRuntimeDir() . DS . 'node_modules' . DS . 'playwright');
        if ($package === false) {
            return null;
        }
        $file = dirname($package) . DS . 'playwright-core' . DS . 'browsers.json';
        if (!is_file($file)) {
            // A globally installed package keeps its dependencies under its own node_modules
            $file = $package . DS . 'node_modules' . DS . 'playwright-core' . DS . 'browsers.json';
        }
        if (!is_file($file)) {
            return null;
        }
        try {
            $data = Mage::helper('core')->jsonDecode((string) file_get_contents($file));
        } catch (\JsonException) {
            return null;
        }
        foreach ($data['browsers'] ?? [] as $entry) {
            if (($entry['name'] ?? null) === $browser->value && isset($entry['revision'])) {
                return (string) $entry['revision'];
            }
        }
        return null;
    }

    /**
     * Install or update the runtime: writes the shared package.json (the
     * union of every registered scanner's packages), runs npm install when
     * the dependency set changed, links a matching external Playwright
     * package when one exists, and downloads the browser build when missing.
     *
     * @param array<string, string> $packages
     */
    public function install(Browser $browser, array $packages = [], bool $force = false): void
    {
        if (!$force && $this->isInstalled($browser, $packages)) {
            return;
        }
        $dir = $this->getRuntimeDir();
        $this->acquireInstallLock();

        try {
            $external = $this->findExternalPlaywright();
            $modules = $dir . DS . 'node_modules';
            $packageJson = $dir . DS . 'package.json';

            if (!$this->isOwnManifest($packageJson)) {
                Mage::throwException(Mage::helper('core')->__('%s belongs to another project. Choose an empty runtime directory.', $packageJson));
            }

            $dependencies = $packages;
            foreach (self::scanners() as $scanner) {
                $dependencies += $scanner->packages();
            }
            unset($dependencies['playwright'], $dependencies['playwright-core']);
            // A linked package resolves its own playwright-core through its real
            // path; the pinned copy here only satisfies peer dependencies
            $dependencies[$external === null ? 'playwright' : 'playwright-core'] = self::getPlaywrightVersion();
            ksort($dependencies);

            $manifest = Mage::helper('core')->jsonEncode([
                'name' => self::MANIFEST_NAME,
                'private' => true,
                'type' => 'module',
                'dependencies' => $dependencies,
            ]);
            $changed = !is_file($packageJson) || file_get_contents($packageJson) !== $manifest;
            if ($changed) {
                file_put_contents($packageJson, $manifest);
            }

            $missing = array_filter(array_keys($dependencies), fn(string $name) => !is_dir($modules . DS . $name));
            if ($force || $changed || $missing !== []) {
                $this->run([$this->getNpmPath(), 'install', '--no-audit', '--no-fund'], self::INSTALL_TIMEOUT, $dir);
            }

            // npm prunes entries it does not own, so relink after every install
            if ($external !== null) {
                $this->link($external, $modules . DS . 'playwright');
            }

            if ($force || !$this->isBrowserInstalled($browser)) {
                $this->run(
                    [$this->getNodePath(), $modules . DS . 'playwright' . DS . 'cli.js', 'install', $browser->value],
                    self::INSTALL_TIMEOUT,
                    $dir,
                );
            }
        } finally {
            Mage::getSingleton('core/lock')->release(self::INSTALL_LOCK);
        }
    }

    /**
     * Real path of a Playwright package outside the runtime dir whose version
     * equals the pin: the repository's own node_modules first, then the
     * global npm prefix. Null when neither matches.
     */
    public function findExternalPlaywright(): ?string
    {
        $candidates = [Mage::getBaseDir() . DS . 'node_modules'];
        $global = $this->getGlobalNodeModules();
        if ($global !== null) {
            $candidates[] = $global;
        }

        $own = realpath($this->getRuntimeDir() . DS . 'node_modules');
        foreach ($candidates as $modules) {
            $package = realpath($modules . DS . 'playwright');
            if ($package === false || ($own !== false && dirname($package) === $own)) {
                continue;
            }
            if ($this->readPackageVersion($package) === self::getPlaywrightVersion()) {
                return $package;
            }
        }
        return null;
    }

    /**
     * Copy a scanner script next to node_modules (so its imports resolve)
     * when missing or outdated, and return the installed path. The write is
     * atomic (temp file + rename) so a concurrent scan never sees a partially
     * written script.
     */
    public function syncScript(string $source, string $name): string
    {
        $dir = $this->getRuntimeDir();
        $target = $dir . DS . $name;
        if (is_file($target) && hash_file('xxh128', $target) === hash_file('xxh128', $source)) {
            return $target;
        }

        $this->acquireInstallLock();
        try {
            $tmp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (!copy($source, $tmp) || !rename($tmp, $target)) {
                @unlink($tmp);
                Mage::throwException(Mage::helper('core')->__('Unable to copy the scanner script to %s', $dir));
            }
        } finally {
            Mage::getSingleton('core/lock')->release(self::INSTALL_LOCK);
        }
        return $target;
    }

    /**
     * Run an external command with the runtime environment, enforcing a
     * wall-clock timeout, and return its stdout
     *
     * @param list<string> $command
     */
    public function run(array $command, int $timeout, ?string $cwd = null): string
    {
        $cwd ??= $this->getRuntimeDir();
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        $env[self::BROWSERS_PATH_ENV] = $this->getBrowsersDir();
        // npm re-invokes node, so the resolved node binary's directory must
        // be on the child PATH even when the web-server PATH lacks it
        $nodePath = Mage::findExecutable($this->getNodePath());
        $env['PATH'] = implode(PATH_SEPARATOR, array_unique(array_filter([
            ...explode(PATH_SEPARATOR, (string) ($env['PATH'] ?? '')),
            $nodePath !== null ? dirname($nodePath) : '',
        ])));

        // proc_open() resolves a bare binary name against the parent process
        // PATH, not the child $env, so resolve it ourselves
        $command[0] = Mage::findExecutable($command[0]) ?? $command[0];

        $helper = Mage::helper('core');
        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            Mage::throwException($helper->__('Unable to start process: %s', implode(' ', $command)));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $timeout;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 200000) > 0) {
                foreach ($read as $stream) {
                    $chunk = (string) fread($stream, 65536);
                    if ($stream === $pipes[1]) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                Mage::throwException($helper->__(
                    'Command timed out after %s seconds: %s',
                    $timeout,
                    implode(' ', $command),
                ));
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($status['exitcode'] !== 0) {
            Mage::throwException($helper->__(
                'Command failed (exit code %s): %s',
                $status['exitcode'],
                trim(mb_substr($stderr !== '' ? $stderr : $stdout, -2000)),
            ));
        }

        return $stdout;
    }

    /**
     * Serialize install/update across concurrent scans so npm install and the
     * script copy cannot race; machine-local because the runtime dir is too
     */
    private function acquireInstallLock(): void
    {
        /** @var \Mage_Core_Model_Lock $lock */
        $lock = Mage::getSingleton('core/lock');
        if (!$lock->acquire(self::INSTALL_LOCK, blocking: true, machineLocal: true)) {
            Mage::throwException(Mage::helper('core')->__('Unable to acquire the browser runtime install lock'));
        }
    }

    private function getGlobalNodeModules(): ?string
    {
        if (self::$globalNodeModules === null) {
            self::$globalNodeModules = false;
            if (Mage::findExecutable($this->getNpmPath()) !== null) {
                try {
                    $root = trim($this->run([$this->getNpmPath(), 'root', '-g'], 30, Mage::getBaseDir()));
                    self::$globalNodeModules = $root !== '' && is_dir($root) ? $root : false;
                } catch (\Throwable) {
                    // No global prefix: nothing to link
                }
            }
        }
        return self::$globalNodeModules ?: null;
    }

    private function readPackageVersion(string $package): ?string
    {
        $file = $package . DS . 'package.json';
        if (!is_file($file)) {
            return null;
        }
        try {
            $data = Mage::helper('core')->jsonDecode((string) file_get_contents($file));
        } catch (\JsonException) {
            return null;
        }
        return isset($data['version']) ? (string) $data['version'] : null;
    }

    /** True when the manifest is absent, unreadable, or written by this class */
    private function isOwnManifest(string $packageJson): bool
    {
        if (!is_file($packageJson)) {
            return true;
        }
        try {
            $data = Mage::helper('core')->jsonDecode((string) file_get_contents($packageJson));
        } catch (\JsonException) {
            return true;
        }
        return ($data['name'] ?? self::MANIFEST_NAME) === self::MANIFEST_NAME;
    }

    private function link(string $target, string $link): void
    {
        if (is_link($link)) {
            if (readlink($link) === $target) {
                return;
            }
            unlink($link);
        } elseif (is_dir($link)) {
            \Maho\Io\File::rmdirRecursive($link);
        }
        $this->ensureDir(dirname($link));
        if (!symlink($target, $link)) {
            Mage::throwException(Mage::helper('core')->__('Unable to link %s to %s', $link, $target));
        }
    }

    private function ensureDir(string $dir): string
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Mage::throwException(Mage::helper('core')->__('Unable to create directory %s', $dir));
        }
        return $dir;
    }
}
