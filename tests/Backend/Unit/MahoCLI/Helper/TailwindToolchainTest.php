<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Helper\TailwindToolchain;

uses(Tests\MahoBackendTestCase::class);

function themeBuildRoot(string ...$files): string
{
    $dir = sys_get_temp_dir() . '/maho-theme-build-' . uniqid();
    mkdir($dir, 0755, true);
    foreach ($files as $file) {
        file_put_contents("$dir/$file", '');
    }
    return $dir;
}

/** @param list<string> $installed */
function themeBuildLocator(array $installed): callable
{
    return fn(string $name) => in_array($name, $installed, true) ? "/usr/bin/$name" : null;
}

it('installs with the manager whose lock file the project carries', function () {
    $root = themeBuildRoot('bun.lock');

    expect(TailwindToolchain::resolveInstallCommand($root, themeBuildLocator(['npm', 'bun'])))
        ->toBe(['/usr/bin/bun', 'add', '--dev', ...TailwindToolchain::NPM_PACKAGES]);
});

it('falls back to the first installed manager when no lock file names one', function () {
    $root = themeBuildRoot();

    expect(TailwindToolchain::resolveInstallCommand($root, themeBuildLocator(['bun'])))
        ->toBe(['/usr/bin/bun', 'add', '--dev', ...TailwindToolchain::NPM_PACKAGES])
        ->and(TailwindToolchain::resolveInstallCommand($root, themeBuildLocator(['yarn', 'npm'])))
        ->toBe(['/usr/bin/npm', 'install', '--save-dev', ...TailwindToolchain::NPM_PACKAGES]);
});

it('ignores a lock file whose manager is not installed', function () {
    $root = themeBuildRoot('pnpm-lock.yaml');

    expect(TailwindToolchain::resolveInstallCommand($root, themeBuildLocator(['npm'])))
        ->toBe(['/usr/bin/npm', 'install', '--save-dev', ...TailwindToolchain::NPM_PACKAGES]);
});

it('installs the pinned versions when package.json already declares the toolchain', function () {
    $root = themeBuildRoot('package-lock.json');
    file_put_contents("$root/package.json", '{"devDependencies":{"@tailwindcss/cli":"^4.3.3"}}');

    expect(TailwindToolchain::resolveInstallCommand($root, themeBuildLocator(['npm'])))
        ->toBe(['/usr/bin/npm', 'install']);
});

it('reports no manager when none is installed', function () {
    expect(TailwindToolchain::resolveInstallCommand(themeBuildRoot(), themeBuildLocator([])))->toBeNull();
});

it('installs the majors that the package.json of Maho uses', function () {
    $package = Mage::helper('core')->jsonDecode((string) file_get_contents(MAHO_ROOT_DIR . '/package.json'));
    $versions = [...($package['dependencies'] ?? []), ...($package['devDependencies'] ?? [])];

    foreach (TailwindToolchain::NPM_PACKAGES as $requirement) {
        [$name, $range] = explode('@', ltrim($requirement, '@'), 2);
        $name = str_starts_with($requirement, '@') ? '@' . $name : $name;

        expect($versions)->toHaveKey($name)
            ->and(preg_replace('/^\D*(\d+).*$/', '$1', $versions[$name]))->toBe(preg_replace('/^\D*(\d+).*$/', '$1', $range));
    }
});
