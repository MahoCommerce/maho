<?php

/**
 * Compiles the CSS sources (src/) of the themes of one area into their deployable css/ bundles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use MahoCLI\Helper\FileChangeWatcher;
use MahoCLI\Helper\TailwindToolchain;
use MahoCLI\Helper\ThemeContentReport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class AbstractThemeBuild extends BaseMahoCommand
{
    protected const SKIN_PATH = 'public/skin/frontend';
    private const BUILD_TIMEOUT = 600;

    protected function compileThemes(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ?string $themeFilter,
        bool $watch,
        bool $sourceMap,
        bool $buildContent,
    ): int {
        $entries = $this->discoverEntries($themeFilter);
        if (!$entries) {
            if ($themeFilter !== null) {
                $io->error([
                    "No buildable sources found for theme '{$themeFilter}'.",
                    'A buildable theme has ' . static::SKIN_PATH . "/{$themeFilter}/src/*.css files",
                    'whose names do not start with an underscore.',
                ]);
                return Command::FAILURE;
            }
            $io->warning([
                'Nothing to build: no theme sources found under ' . static::SKIN_PATH . '/*/*/src/.',
                'Themes without a src/ directory need no build - their css/ files (theme.css, ...) are served as-is.',
            ]);
            return Command::SUCCESS;
        }

        $tailwind = TailwindToolchain::resolveBinary($io, $input->isInteractive());
        if ($tailwind === null) {
            return Command::FAILURE;
        }

        if ($watch) {
            return $this->watch($entries, $tailwind, $io, $output, $sourceMap, $buildContent && $this->initContentBuild($io));
        }
        $result = $this->build($entries, $tailwind, $io, $sourceMap);
        if ($result === Command::SUCCESS && $buildContent && $this->initContentBuild($io)) {
            $this->buildContent($io);
        }
        return $result;
    }

    /**
     * Initialize Maho for the build of the content classes. It gives false, with a message,
     * when Maho is not installed or the database is not available, so a build in CI or on a
     * checkout with no store still works.
     */
    private function initContentBuild(SymfonyStyle $io): bool
    {
        try {
            $this->initMaho();
            if (!Mage::isInstalled()) {
                $io->text('Maho is not installed, so the classes of the content were not compiled.');
                return false;
            }
            Mage::getSingleton('core/design_content_bundle')->getManifest();
        } catch (\Throwable $e) {
            $io->warning([
                'The classes of the content were not compiled: ' . $e->getMessage(),
                'Run "./maho theme:content:build" when the database is available.',
            ]);
            return false;
        }
        return true;
    }

    /**
     * Compile the classes of the content into the copy that the store serves. A failure gives a
     * message, and the compiled theme bundles stay valid.
     */
    private function buildContent(SymfonyStyle $io): void
    {
        try {
            ThemeContentReport::build($io);
        } catch (\Throwable $e) {
            $io->warning('The classes of the content were not compiled: ' . $e->getMessage());
        }
    }

    /**
     * Find build entries: every top-level src/*.css file of every theme skin,
     * except the _-prefixed partials that the entries import or @reference.
     *
     * @return list<array{src: string, out: string, theme: string, bundle: string}>
     */
    private function discoverEntries(?string $themeFilter): array
    {
        $skinDir = MAHO_ROOT_DIR . '/' . static::SKIN_PATH;
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

            $name = basename($srcFile, '.css');
            if (str_starts_with($name, '_')) {
                continue;
            }

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

    /**
     * @param list<array{src: string, out: string, theme: string, bundle: string}> $entries
     */
    private function build(array $entries, string $tailwind, SymfonyStyle $io, bool $sourceMap): int
    {
        $failures = 0;
        foreach ($entries as $entry) {
            $args = [$tailwind, '-i', $entry['src'], '-o', $entry['out'], '--minify'];
            if ($sourceMap) {
                $args[] = '--map';
            }
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

            $io->text(sprintf(
                '<info>✓</info> %s → %s',
                $this->relativePath($entry['src']),
                $this->relativePath($entry['out']),
            ));
        }

        if ($failures > 0) {
            $io->error("{$failures} of " . count($entries) . ' bundles failed to compile.');
            return Command::FAILURE;
        }

        $themes = array_unique(array_column($entries, 'theme'));
        $io->success(
            'Compiled ' . count($entries) . ' CSS bundle(s) for ' . implode(', ', $themes)
            . ($sourceMap
                ? '. The source map is inline: rebuild without --map before committing.'
                : '. The compiled css/ files are meant to be committed.'),
        );
        return Command::SUCCESS;
    }

    /**
     * @param list<array{src: string, out: string, theme: string, bundle: string}> $entries
     */
    private function watch(
        array $entries,
        string $tailwind,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $sourceMap,
        bool $buildContent,
    ): int {
        $themes = array_unique(array_column($entries, 'theme'));
        $io->text([
            'Watching ' . count($entries) . ' source(s) of ' . implode(', ', $themes) . ' - press Ctrl+C to stop.',
            '<comment>Watch output is unminified: run ' . $this->getName() . ' (without --watch) before committing.</comment>',
            $sourceMap
                ? '<comment>Source maps are inline: devtools list the src/ partials. Rebuild without --map before committing.</comment>'
                : '<comment>Add --map to have devtools list the src/ partials instead of the bundle.</comment>',
            '',
        ]);

        $processes = [];
        foreach ($entries as $entry) {
            // =always keeps the watcher alive when stdin is closed, which it is under Process
            $args = [$tailwind, '-i', $entry['src'], '-o', $entry['out'], '--watch=always'];
            if ($sourceMap) {
                $args[] = '--map';
            }
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

        $stylesFiles = array_column(array_filter($entries, fn(array $entry): bool => $entry['bundle'] === 'styles.css'), 'out');
        $watcher = $buildContent && $stylesFiles !== [] ? new FileChangeWatcher(array_values($stylesFiles)) : null;
        // The copy can come from an older styles.css than the one that the watch starts with
        $watcher?->markChanged(microtime(true));
        do {
            usleep(200_000);
            $running = array_filter($processes, fn(Process $process) => $process->isRunning());
            if ($watcher?->poll(microtime(true))) {
                $this->buildContentQuietly($output);
            }
        } while ($running !== []);

        return Command::SUCCESS;
    }

    /**
     * Compile the classes of the content, and print one line for each theme.
     */
    private function buildContentQuietly(OutputInterface $output): void
    {
        try {
            $status = Mage::getSingleton('core/design_content_bundle')->build();
        } catch (\Throwable $e) {
            $output->writeln('[content] <error>' . $e->getMessage() . '</error>');
            return;
        }
        if ($status === null) {
            $output->writeln('[content] another build is running, so this change waits for the next one');
            return;
        }
        foreach ($status as $theme => $result) {
            $output->writeln("[content] {$theme}: {$result}");
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace(MAHO_ROOT_DIR . '/', '', $path);
    }
}
