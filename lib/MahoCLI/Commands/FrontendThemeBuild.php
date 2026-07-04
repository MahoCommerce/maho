<?php

/**
 * Compiles frontend theme CSS sources (src/) into their deployable css/ bundles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'dev:frontend:theme:build',
    description: 'Compile the CSS sources (src/*.css) of every frontend theme into their css/ bundles',
)]
class FrontendThemeBuild extends BaseMahoCommand
{
    private const SKIN_PATH = 'public/skin/frontend';
    private const NPM_PACKAGES = ['tailwindcss', '@tailwindcss/cli', 'daisyui'];
    private const BUILD_TIMEOUT = 600;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'Limit the build to one theme instead of all, as package/theme (e.g. --theme maho/pharmacy)')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Rebuild on change; output is unminified, run a plain build before committing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeFilter = $input->getOption('theme');

        $entries = $this->discoverEntries($themeFilter);
        if (!$entries) {
            if ($themeFilter !== null) {
                $io->error([
                    "No buildable sources found for theme '{$themeFilter}'.",
                    'A buildable theme has ' . self::SKIN_PATH . "/{$themeFilter}/src/*.css files",
                    'that @import "tailwindcss" or @reference the shared theme.',
                ]);
                return Command::FAILURE;
            }
            $io->warning([
                'Nothing to build: no theme sources found under ' . self::SKIN_PATH . '/*/*/src/.',
                'Themes without a src/ directory need no build - their css/ files (theme.css, ...) are served as-is.',
            ]);
            return Command::SUCCESS;
        }

        $tailwind = $this->resolveTailwindBinary($io);
        if ($tailwind === null) {
            return Command::FAILURE;
        }

        if ($input->getOption('watch')) {
            return $this->watch($entries, $tailwind, $io, $output);
        }
        return $this->build($entries, $tailwind, $io);
    }

    /**
     * Find build entries: top-level src/*.css files of every theme skin.
     * A file is an entry when it drives a Tailwind compilation itself
     * (@import "tailwindcss" or @reference "..."); files that are only
     * imported by an entry (e.g. maho/default's components.css) are skipped.
     *
     * @return list<array{src: string, out: string, theme: string, bundle: string}>
     */
    private function discoverEntries(?string $themeFilter): array
    {
        $skinDir = MAHO_ROOT_DIR . '/' . self::SKIN_PATH;
        $entries = [];

        foreach (glob("{$skinDir}/*/*/src/*.css") ?: [] as $srcFile) {
            $parts = explode('/', substr($srcFile, strlen($skinDir) + 1));
            if (count($parts) !== 4) {
                continue;
            }
            [$package, $theme] = $parts;

            if ($themeFilter !== null && $themeFilter !== "{$package}/{$theme}") {
                continue;
            }
            if (!$this->isBuildEntry($srcFile)) {
                continue;
            }

            $name = basename($srcFile, '.css');
            $bundle = ($name === 'tailwind' ? 'styles' : $name) . '.css';
            $entries[] = [
                'src' => $srcFile,
                'out' => dirname($srcFile, 2) . "/css/{$bundle}",
                'theme' => "{$package}/{$theme}",
                'bundle' => $bundle,
            ];
        }

        return $entries;
    }

    private function isBuildEntry(string $file): bool
    {
        $head = (string) file_get_contents($file, length: 8192);
        return (bool) preg_match('/@import\s+["\']tailwindcss|@reference\s/', $head);
    }

    /**
     * Locate the Tailwind CLI binary, offering to install the npm toolchain
     * when missing. Works both in the Maho repo and in child projects that
     * use Maho as a Composer dependency (MAHO_ROOT_DIR is the project root
     * in both cases; npm installs into its node_modules/).
     */
    private function resolveTailwindBinary(SymfonyStyle $io): ?string
    {
        $binary = MAHO_ROOT_DIR . '/node_modules/.bin/tailwindcss';
        if (is_file($binary)) {
            return $binary;
        }

        $npm = (new ExecutableFinder())->find('npm');
        if ($npm === null) {
            $io->error([
                'Node.js/npm is required to compile theme sources - install it from https://nodejs.org',
                'and run this command again.',
                'This is only needed when editing src/*.css files: store-owner theming',
                'via css/theme.css needs no build at all.',
            ]);
            return null;
        }

        $installCommand = $this->npmInstallCommand($npm);
        $io->text('The Tailwind CSS toolchain is not installed in ' . MAHO_ROOT_DIR . '/node_modules.');
        if (!$io->confirm('Run "' . implode(' ', $installCommand) . '" now?')) {
            $io->text('Aborted. Install the toolchain, then run this command again.');
            return null;
        }

        $process = new Process($installCommand, MAHO_ROOT_DIR, null, null, self::BUILD_TIMEOUT);
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });
        if (!$process->isSuccessful() || !is_file($binary)) {
            $io->error('npm install failed - see the output above.');
            return null;
        }

        return $binary;
    }

    /**
     * @return list<string>
     */
    private function npmInstallCommand(string $npm): array
    {
        $packageJson = MAHO_ROOT_DIR . '/package.json';
        if (is_file($packageJson) && str_contains((string) file_get_contents($packageJson), '"@tailwindcss/cli"')) {
            return [$npm, 'install'];
        }
        return [$npm, 'install', '--save-dev', ...self::NPM_PACKAGES];
    }

    /**
     * @param list<array{src: string, out: string, theme: string, bundle: string}> $entries
     */
    private function build(array $entries, string $tailwind, SymfonyStyle $io): int
    {
        $failures = 0;
        foreach ($entries as $entry) {
            $args = [$tailwind, '-i', $entry['src'], '-o', $entry['out'], '--minify'];
            $process = new Process($args, MAHO_ROOT_DIR, null, null, self::BUILD_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error([
                    "Failed to compile {$this->relativePath($entry['src'])}:",
                    trim($process->getErrorOutput() . "\n" . $process->getOutput()),
                ]);
                $failures++;
                continue;
            }

            clearstatcache(true, $entry['out']);
            $io->text(sprintf(
                '<info>✓</info> %s → %s (%s)',
                $this->relativePath($entry['src']),
                $this->relativePath($entry['out']),
                $this->humanReadableSize((int) filesize($entry['out'])),
            ));
        }

        if ($failures > 0) {
            $io->error("{$failures} of " . count($entries) . ' bundles failed to compile.');
            return Command::FAILURE;
        }

        $themes = array_unique(array_column($entries, 'theme'));
        $io->success(
            'Compiled ' . count($entries) . ' CSS bundle(s) for ' . implode(', ', $themes)
            . '. The compiled css/ files are meant to be committed.',
        );
        return Command::SUCCESS;
    }

    /**
     * @param list<array{src: string, out: string, theme: string, bundle: string}> $entries
     */
    private function watch(array $entries, string $tailwind, SymfonyStyle $io, OutputInterface $output): int
    {
        $themes = array_unique(array_column($entries, 'theme'));
        $io->text([
            'Watching ' . count($entries) . ' source(s) of ' . implode(', ', $themes) . ' - press Ctrl+C to stop.',
            '<comment>Watch output is unminified: run dev:frontend:theme:build (without --watch) before committing.</comment>',
            '',
        ]);

        $processes = [];
        foreach ($entries as $entry) {
            // =always keeps the watcher alive when stdin is closed, which it is under Process
            $args = [$tailwind, '-i', $entry['src'], '-o', $entry['out'], '--watch=always'];
            $label = "{$entry['theme']}:{$entry['bundle']}";
            $process = new Process($args, MAHO_ROOT_DIR, null, null, null);
            $process->start(function ($type, $buffer) use ($output, $label) {
                foreach (preg_split('/\R/', trim($buffer)) ?: [] as $line) {
                    if ($line !== '') {
                        $output->writeln("[{$label}] {$line}");
                    }
                }
            });
            $processes[] = $process;
        }

        do {
            usleep(200_000);
            $running = array_filter($processes, fn(Process $process) => $process->isRunning());
        } while ($running !== []);

        return Command::SUCCESS;
    }

    private function relativePath(string $path): string
    {
        return str_replace(MAHO_ROOT_DIR . '/', '', $path);
    }
}
