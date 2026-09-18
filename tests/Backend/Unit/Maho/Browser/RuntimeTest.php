<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Browser\Browser;
use Maho\Browser\Runtime;

uses(Tests\MahoBackendTestCase::class);

/**
 * Lay out a fake runtime directory: a playwright package of the given version
 * with a browsers.json, optional extra packages and optional browser builds
 *
 * @param list<string> $packages
 * @param list<Browser> $browsers
 */
function browserRuntimeFixture(string $dir, ?string $version, array $packages = [], array $browsers = [], string $revision = '1223'): void
{
    if ($version !== null) {
        mkdir("$dir/node_modules/playwright", 0755, true);
        mkdir("$dir/node_modules/playwright-core", 0755, true);
        file_put_contents("$dir/node_modules/playwright/package.json", json_encode(['name' => 'playwright', 'version' => $version]));
        file_put_contents("$dir/node_modules/playwright-core/browsers.json", json_encode(['browsers' => [
            ['name' => 'chromium', 'revision' => $revision],
            ['name' => 'chromium-headless-shell', 'revision' => $revision],
        ]]));
    }
    foreach ($packages as $package) {
        mkdir("$dir/node_modules/$package", 0755, true);
    }
    foreach ($browsers as $browser) {
        mkdir("$dir/browsers/" . $browser->directoryPrefix() . "-$revision", 0755, true);
    }
}

describe('Browser runtime', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir() . '/maho_browser_runtime_' . uniqid();
        mkdir($this->dir, 0755, true);
        $this->runtime = new Runtime();
        putenv(Runtime::RUNTIME_DIR_ENV . '=' . $this->dir);
        putenv(Runtime::BROWSERS_PATH_ENV);
    });

    afterEach(function () {
        putenv(Runtime::RUNTIME_DIR_ENV);
        putenv(Runtime::BROWSERS_PATH_ENV);
        Mage::app()->getStore()->setConfig('system/browser/runtime_dir', '');
        \Maho\Io\File::rmdirRecursive($this->dir);
    });

    it('pins the Playwright version the test suite uses', function () {
        $lock = json_decode((string) file_get_contents(dirname(__DIR__, 5) . '/package-lock.json'), true);
        expect(Runtime::getPlaywrightVersion())->toBe($lock['packages']['node_modules/playwright']['version']);
        expect(Runtime::getPlaywrightVersion())->toMatch('/^\d+\.\d+\.\d+$/');
    });

    it('resolves the runtime dir from the environment, then the config, then var', function () {
        expect($this->runtime->getRuntimeDir())->toBe($this->dir);

        putenv(Runtime::RUNTIME_DIR_ENV);
        Mage::app()->getStore()->setConfig('system/browser/runtime_dir', $this->dir . '/configured');
        expect($this->runtime->getRuntimeDir())->toBe($this->dir . '/configured');

        Mage::app()->getStore()->setConfig('system/browser/runtime_dir', '');
        expect($this->runtime->getRuntimeDir())->toBe(Mage::getBaseDir('var') . DS . 'browser-runtime');
    });

    it('keeps browsers next to the runtime unless PLAYWRIGHT_BROWSERS_PATH is set', function () {
        expect($this->runtime->getBrowsersDir())->toBe($this->dir . DS . 'browsers');

        putenv(Runtime::BROWSERS_PATH_ENV . '=/opt/ms-playwright');
        expect($this->runtime->getBrowsersDir())->toBe('/opt/ms-playwright');
    });

    it('maps browsers to their Playwright install names and directory prefixes', function () {
        expect(Browser::HeadlessShell->value)->toBe('chromium-headless-shell');
        expect(Browser::HeadlessShell->directoryPrefix())->toBe('chromium_headless_shell');
        expect(Browser::Chromium->directoryPrefix())->toBe('chromium');
        expect(Browser::fromOption('headless-shell'))->toBe(Browser::HeadlessShell);
        expect(Browser::fromOption('Chromium'))->toBe(Browser::Chromium);
        expect(fn() => Browser::fromOption('firefox'))->toThrow(InvalidArgumentException::class);
    });

    it('reports an empty directory as not installed', function () {
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeFalse();
        expect($this->runtime->getBrowserRevision(Browser::HeadlessShell))->toBeNull();
    });

    it('rejects a Playwright package whose version differs from the pin', function () {
        browserRuntimeFixture($this->dir, '1.40.0', [], [Browser::HeadlessShell]);
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeFalse();
    });

    it('requires every scanner package', function () {
        browserRuntimeFixture($this->dir, Runtime::getPlaywrightVersion(), [], [Browser::HeadlessShell]);
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeTrue();
        expect($this->runtime->isInstalled(Browser::HeadlessShell, ['@axe-core/playwright' => '^4']))->toBeFalse();

        mkdir($this->dir . '/node_modules/@axe-core/playwright', 0755, true);
        expect($this->runtime->isInstalled(Browser::HeadlessShell, ['@axe-core/playwright' => '^4']))->toBeTrue();
    });

    it('checks the browser build the package expects, per browser', function () {
        browserRuntimeFixture($this->dir, Runtime::getPlaywrightVersion(), [], [Browser::HeadlessShell], '1300');
        expect($this->runtime->getBrowserRevision(Browser::Chromium))->toBe('1300');
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeTrue();
        expect($this->runtime->isInstalled(Browser::Chromium))->toBeFalse();

        mkdir($this->dir . '/browsers/chromium-1300');
        expect($this->runtime->isInstalled(Browser::Chromium))->toBeTrue();
    });

    it('honours a prebuilt browser cache from the environment', function () {
        browserRuntimeFixture($this->dir, Runtime::getPlaywrightVersion());
        mkdir($this->dir . '/prebuilt/chromium_headless_shell-1223', 0755, true);
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeFalse();

        putenv(Runtime::BROWSERS_PATH_ENV . '=' . $this->dir . '/prebuilt');
        expect($this->runtime->isInstalled(Browser::HeadlessShell))->toBeTrue();
    });

    it('follows a linked external package to its own browsers.json', function () {
        browserRuntimeFixture($this->dir . '/external', Runtime::getPlaywrightVersion(), [], [], '1500');
        mkdir($this->dir . '/node_modules', 0755, true);
        symlink($this->dir . '/external/node_modules/playwright', $this->dir . '/node_modules/playwright');

        expect($this->runtime->getBrowserRevision(Browser::HeadlessShell))->toBe('1500');
    });

    it('does not report its own runtime dir as an external package', function () {
        browserRuntimeFixture($this->dir, Runtime::getPlaywrightVersion());
        $external = $this->runtime->findExternalPlaywright();
        expect($external === null || !str_starts_with($external, realpath($this->dir)))->toBeTrue();
    });

    it('refuses a runtime directory whose package.json belongs to another project', function () {
        file_put_contents($this->dir . '/package.json', json_encode(['name' => 'some-other-project']));
        expect(fn() => $this->runtime->install(Browser::HeadlessShell))
            ->toThrow(Mage_Core_Exception::class, 'belongs to another project');
    });

    it('reports requirement issues when the configured binaries do not exist', function () {
        Mage::app()->getStore()->setConfig('system/browser/node_path', '/path/to/nowhere/node');
        Mage::app()->getStore()->setConfig('system/browser/npm_path', '/path/to/nowhere/npm');

        $issues = $this->runtime->getRequirementIssues();
        expect($issues)->toHaveCount(2);
        expect($issues[0])->toContain('Node.js was not found');
        expect($issues[1])->toContain('npm was not found');
        expect($this->runtime->getNodeVersion())->toBeNull();

        Mage::app()->getStore()->setConfig('system/browser/node_path', 'node');
        Mage::app()->getStore()->setConfig('system/browser/npm_path', 'npm');
    });

    it('registers the accessibility scanner', function () {
        $scanners = Runtime::scanners();
        expect($scanners)->toHaveKey('accessibilityscan');
        expect($scanners['accessibilityscan']->browser())->toBe(Browser::HeadlessShell);
        expect($scanners['accessibilityscan']->packages())->toHaveKey('@axe-core/playwright');
    });
});
