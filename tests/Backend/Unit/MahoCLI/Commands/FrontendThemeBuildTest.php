<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\FrontendThemeBuild;

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

    expect(FrontendThemeBuild::resolveInstallCommand($root, themeBuildLocator(['npm', 'bun'])))
        ->toBe(['/usr/bin/bun', 'add', '--dev', 'tailwindcss', '@tailwindcss/cli', 'daisyui']);
});

it('falls back to the first installed manager when no lock file names one', function () {
    $root = themeBuildRoot();

    expect(FrontendThemeBuild::resolveInstallCommand($root, themeBuildLocator(['bun'])))
        ->toBe(['/usr/bin/bun', 'add', '--dev', 'tailwindcss', '@tailwindcss/cli', 'daisyui'])
        ->and(FrontendThemeBuild::resolveInstallCommand($root, themeBuildLocator(['yarn', 'npm'])))
        ->toBe(['/usr/bin/npm', 'install', '--save-dev', 'tailwindcss', '@tailwindcss/cli', 'daisyui']);
});

it('ignores a lock file whose manager is not installed', function () {
    $root = themeBuildRoot('pnpm-lock.yaml');

    expect(FrontendThemeBuild::resolveInstallCommand($root, themeBuildLocator(['npm'])))
        ->toBe(['/usr/bin/npm', 'install', '--save-dev', 'tailwindcss', '@tailwindcss/cli', 'daisyui']);
});

it('installs the pinned versions when package.json already declares the toolchain', function () {
    $root = themeBuildRoot('package-lock.json');
    file_put_contents("$root/package.json", '{"devDependencies":{"@tailwindcss/cli":"^4.3.3"}}');

    expect(FrontendThemeBuild::resolveInstallCommand($root, themeBuildLocator(['npm'])))
        ->toBe(['/usr/bin/npm', 'install']);
});

it('reports no manager when none is installed', function () {
    expect(FrontendThemeBuild::resolveInstallCommand(themeBuildRoot(), themeBuildLocator([])))->toBeNull();
});
