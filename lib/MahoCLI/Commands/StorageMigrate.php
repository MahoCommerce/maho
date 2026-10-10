<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Storage\MigrationResult;
use Maho\Storage\Migrator;
use Maho\Storage\MountRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'storage:migrate',
    description: 'Copy the local folder of storage mounts to the adapter that local.xml configures, such as an S3 bucket',
)]
class StorageMigrate extends BaseMahoCommand
{
    /** A bucket answers each put in about 250 ms, so the copy to a bucket runs this many jobs. */
    public const DEFAULT_REMOTE_JOBS = 16;

    private const PART_FILE_LINE = 'file';

    private const PART_RESULT_PREFIX = 'result ';

    /**
     * @param list<string> $mounts
     * @param list<string> $exclude
     */
    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument(description: 'Mounts to migrate, for example media. Default: every mount that local.xml points at a remote adapter')]
        array $mounts = [],
        #[Option(description: 'Show what would be copied, and copy nothing')]
        bool $dryRun = false,
        #[Option(description: 'Also copy the caches that Maho creates again on demand, such as the resized product images')]
        bool $includeCache = false,
        #[Option(description: 'A folder of the mount that is not copied. Repeat it for more folders')]
        array $exclude = [],
        #[Option(description: 'The number of parallel jobs. Default: 1 to a local folder, ' . self::DEFAULT_REMOTE_JOBS . ' to a bucket')]
        ?int $jobs = null,
        #[Option(description: 'Copy the files that stdin lists as JSON, and print the counts as JSON. The parallel jobs use it')]
        bool $filesFromStdin = false,
    ): int {
        $this->initMaho();

        if ($filesFromStdin) {
            return $this->copyFilesFromStdin($io, $input, $mounts, $dryRun);
        }

        if ($mounts === []) {
            $mounts = array_values(array_filter(
                MountRegistry::names(),
                fn(string $name): bool => !MountRegistry::get($name)->isLocal(),
            ));
            if ($mounts === []) {
                $io->warning('Every mount uses the local disk. Point a mount at a bucket in local.xml first.');
                return Command::SUCCESS;
            }
        }

        $status = Command::SUCCESS;
        foreach ($mounts as $name) {
            if (!MountRegistry::has($name)) {
                $io->error("Unknown mount \"{$name}\". Known mounts: " . implode(', ', MountRegistry::names()));
                $status = Command::FAILURE;
                continue;
            }

            if (isset(Migrator::NOT_COPIED[$name])) {
                $io->warning("The mount \"{$name}\" is not copied. " . Migrator::NOT_COPIED[$name]);
                continue;
            }

            $target = MountRegistry::get($name);
            $source = MountRegistry::getDeclaredLocalMount($name);
            if ($source === null || ($target->isLocal() && realpath((string) $target->localRoot()) === realpath((string) $source->localRoot()))) {
                $io->warning("The mount \"{$name}\" uses its local folder already, so there is nothing to copy.");
                continue;
            }

            $skip = $this->excludedFolders($name, $exclude, $includeCache);

            $io->section(($dryRun ? 'Dry run: ' : '') . "{$name}: {$source->localRoot()} to the configured adapter");
            if ($skip !== []) {
                $io->text('Not copied: ' . implode(', ', $skip));
            }

            $jobCount = max(1, $jobs ?? ($target->isLocal() ? 1 : self::DEFAULT_REMOTE_JOBS));
            $migrator = new Migrator();
            $io->text('Step 1 of 2: list the files that the target holds already.');
            $listing = $io->createProgressBar();
            $listing->setFormat(' %current% files | elapsed %elapsed%');
            $listing->start();
            $existing = $migrator->listFiles($target, $skip, fn() => $listing->advance());
            $listing->finish();
            $io->newLine(2);

            $io->text('Step 2 of 2: ' . ($dryRun ? 'find' : 'copy') . ' the missing files' . ($jobCount > 1 ? " with {$jobCount} jobs." : '.'));
            $progress = $io->createProgressBar($migrator->countFiles($source, $skip));
            $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% | elapsed %elapsed% | left %remaining%');
            $progress->start();
            $result = new MigrationResult();
            $files = $migrator->findMissingFiles($source, $existing, $skip, $result, fn() => $progress->advance());
            unset($existing);
            if ($jobCount === 1) {
                $migrator->copyFiles($source, $target, $files, $dryRun, $result, fn() => $progress->advance());
            } else {
                $this->runJobs($progress, $result, $name, $files, $jobCount, $dryRun);
            }
            $progress->finish();
            $io->newLine(2);

            $io->definitionList(
                [$dryRun ? 'To copy' : 'Copied' => sprintf('%d files, %s', $result->copied, $this->humanReadableSize($result->bytes))],
                ['Already there' => (string) $result->skipped],
                ['Failed' => (string) count($result->failed)],
            );
            foreach ($result->failed as $path => $message) {
                $io->text("<error>{$path}</error>: {$message}");
            }
            if ($result->failed !== []) {
                $io->error('Some files were not copied. Run the command again: it copies only what is missing.');
                $status = Command::FAILURE;
            }
        }

        if ($status === Command::SUCCESS && !$dryRun) {
            $io->success('Done. Check the store before you delete the local folders.');
        }
        return $status;
    }

    /**
     * @param list<string> $exclude
     * @return list<string>
     */
    private function excludedFolders(string $name, array $exclude, bool $includeCache): array
    {
        $skip = array_merge($exclude, Migrator::foldersOfOtherMounts($name));
        if (!$includeCache) {
            $skip = array_merge($skip, Migrator::REGENERATED[$name] ?? []);
        }
        return $skip;
    }

    /**
     * Share $files among $jobCount processes of this command, and add their counts to $result.
     * Each process gets its files as JSON on stdin, so no process lists the target again.
     *
     * @param array<string, int> $files the size of each file to copy, by path
     */
    private function runJobs(ProgressBar $progress, MigrationResult $result, string $name, array $files, int $jobCount, bool $dryRun): void
    {
        $parts = array_fill(0, $jobCount, []);
        $i = 0;
        foreach ($files as $path => $size) {
            $parts[$i++ % $jobCount][$path] = $size;
        }

        $processes = [];
        foreach (array_filter($parts) as $i => $part) {
            $command = [PHP_BINARY, MAHO_ROOT_DIR . '/maho', 'storage:migrate', $name, '--files-from-stdin'];
            if ($dryRun) {
                $command[] = '--dry-run';
            }
            $process = new Process($command, MAHO_ROOT_DIR, null, Mage::helper('core')->jsonEncode($part), null);
            $process->start();
            $processes[$i] = $process;
        }

        $buffers = array_fill_keys(array_keys($processes), '');
        $reported = [];
        while ($processes !== []) {
            foreach ($processes as $i => $process) {
                $running = $process->isRunning();
                $buffers[$i] .= $process->getIncrementalOutput();
                while (($end = strpos($buffers[$i], "\n")) !== false) {
                    $line = substr($buffers[$i], 0, $end);
                    $buffers[$i] = substr($buffers[$i], $end + 1);
                    if ($line === self::PART_FILE_LINE) {
                        $progress->advance();
                    } elseif (str_starts_with($line, self::PART_RESULT_PREFIX)) {
                        $this->addPartResult($result, substr($line, strlen(self::PART_RESULT_PREFIX)));
                        $reported[$i] = true;
                    }
                }
                if ($running) {
                    continue;
                }
                if (!isset($reported[$i])) {
                    $result->failed["(job {$i}/{$jobCount})"] = trim($process->getErrorOutput()) ?: "The job stopped with exit code {$process->getExitCode()}.";
                }
                unset($processes[$i]);
            }
            usleep(50_000);
        }
    }

    private function addPartResult(MigrationResult $result, string $json): void
    {
        /** @var array{copied: int, skipped: int, bytes: int, failed: array<string, string>} $part */
        $part = Mage::helper('core')->jsonDecode($json);
        $result->copied += $part['copied'];
        $result->skipped += $part['skipped'];
        $result->bytes += $part['bytes'];
        $result->failed += $part['failed'];
    }

    /**
     * Copy the files that stdin lists as a JSON object of sizes by path. Print a line for each
     * file, then a line with the counts as JSON.
     *
     * @param list<string> $mounts
     */
    private function copyFilesFromStdin(SymfonyStyle $io, InputInterface $input, array $mounts, bool $dryRun): int
    {
        $name = $mounts[0] ?? '';
        $source = count($mounts) === 1 && MountRegistry::has($name) ? MountRegistry::getDeclaredLocalMount($name) : null;
        if ($source === null) {
            $io->error('--files-from-stdin needs one mount with a local folder.');
            return Command::FAILURE;
        }

        $stdin = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $files = Mage::helper('core')->jsonDecode((string) stream_get_contents($stdin ?? STDIN));
        if (!is_array($files)) {
            $io->error('--files-from-stdin needs a JSON object of sizes by path.');
            return Command::FAILURE;
        }

        $result = new MigrationResult();
        new Migrator()->copyFiles(
            $source,
            MountRegistry::get($name),
            array_map(intval(...), $files),
            $dryRun,
            $result,
            fn() => $io->writeln(self::PART_FILE_LINE, OutputInterface::OUTPUT_RAW),
        );
        $io->writeln(self::PART_RESULT_PREFIX . Mage::helper('core')->jsonEncode([
            'copied' => $result->copied,
            'skipped' => $result->skipped,
            'bytes' => $result->bytes,
            'failed' => $result->failed,
        ]), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
