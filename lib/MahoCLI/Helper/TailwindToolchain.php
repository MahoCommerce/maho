<?php

/**
 * Finds the Tailwind CLI in node_modules, and offers to install it when it is missing.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage_Core_Model_Design_Content_Bundle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TailwindToolchain
{
    /** The majors of the package.json of Maho, so a new major cannot break the build of its sources. */
    public const NPM_PACKAGES = ['tailwindcss@^4', '@tailwindcss/cli@^4', 'daisyui@^5'];

    /** Supported package managers in preference order, with the arguments that add a dev dependency. */
    public const PACKAGE_MANAGERS = [
        'npm' => ['install', '--save-dev'],
        'bun' => ['add', '--dev'],
        'pnpm' => ['add', '-D'],
        'yarn' => ['add', '--dev'],
    ];

    /** @var array<string, list<string>> */
    private const LOCK_FILES = [
        'npm' => ['package-lock.json'],
        'bun' => ['bun.lock', 'bun.lockb'],
        'pnpm' => ['pnpm-lock.yaml'],
        'yarn' => ['yarn.lock'],
    ];
    private const INSTALL_TIMEOUT = 600;

    /**
     * The path of the Tailwind CLI. When it is missing, install the toolchain when $force is
     * true, ask to install it in an interactive terminal, or show the install command otherwise.
     */
    public static function resolveBinary(SymfonyStyle $io, bool $interactive, bool $force): ?string
    {
        $binary = MAHO_ROOT_DIR . '/' . Mage_Core_Model_Design_Content_Bundle::TAILWIND_BINARY;
        if (is_file($binary)) {
            return $binary;
        }

        $finder = new ExecutableFinder();
        $installCommand = self::resolveInstallCommand(MAHO_ROOT_DIR, fn(string $name) => $finder->find($name));
        if ($installCommand === null) {
            $io->error([
                'Compiling theme sources needs one of ' . implode(', ', array_keys(self::PACKAGE_MANAGERS)) . '.',
                'Install Node.js from https://nodejs.org (or Bun from https://bun.sh) and run this command again.',
                'This is only needed when editing src/*.css files or using Tailwind classes in content: store-owner',
                'theming via css/theme.css needs no build at all.',
            ]);
            return null;
        }

        $command = implode(' ', $installCommand);
        if (!$interactive && !$force) {
            $io->error([
                'The Tailwind CSS toolchain is not installed in ' . MAHO_ROOT_DIR . '/node_modules.',
                "Run \"{$command}\" in that folder, or add --force, then run this command again.",
            ]);
            return null;
        }

        $io->text('The Tailwind CSS toolchain is not installed in ' . MAHO_ROOT_DIR . '/node_modules.');
        if ($force) {
            $io->text("Running \"{$command}\".");
        } elseif (!$io->confirm("Run \"{$command}\" now?")) {
            $io->text('Aborted. Install the toolchain, then run this command again.');
            return null;
        }

        $process = new Process($installCommand, MAHO_ROOT_DIR, null, null, self::INSTALL_TIMEOUT);
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });
        if (!$process->isSuccessful() || !is_file($binary)) {
            $io->error($installCommand[0] . ' failed - see the output above.');
            return null;
        }

        return $binary;
    }

    /**
     * Pick the package manager to install with: the one whose lock file the
     * project already carries, else the first one installed. A project that
     * declares the toolchain in package.json gets a plain install, so the
     * pinned versions win over the ones this command would name.
     *
     * @param callable(string): ?string $locate
     * @return list<string>|null
     */
    public static function resolveInstallCommand(string $rootDir, callable $locate): ?array
    {
        $build = function (string $binary, string $manager) use ($rootDir): array {
            $packageJson = $rootDir . '/package.json';
            if (is_file($packageJson) && str_contains((string) file_get_contents($packageJson), '"@tailwindcss/cli"')) {
                return [$binary, 'install'];
            }
            return [$binary, ...self::PACKAGE_MANAGERS[$manager], ...self::NPM_PACKAGES];
        };

        foreach (array_keys(self::PACKAGE_MANAGERS) as $manager) {
            foreach (self::LOCK_FILES[$manager] as $lockFile) {
                if (is_file($rootDir . '/' . $lockFile) && ($binary = $locate($manager)) !== null) {
                    return $build($binary, $manager);
                }
            }
        }

        foreach (array_keys(self::PACKAGE_MANAGERS) as $manager) {
            if (($binary = $locate($manager)) !== null) {
                return $build($binary, $manager);
            }
        }

        return null;
    }
}
