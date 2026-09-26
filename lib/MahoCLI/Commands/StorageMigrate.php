<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Storage\Migrator;
use Maho\Storage\MountRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'storage:migrate',
    description: 'Copy the local folder of storage mounts to the adapter that local.xml configures, such as an S3 bucket',
)]
class StorageMigrate extends BaseMahoCommand
{
    /**
     * @param list<string> $mounts
     * @param list<string> $exclude
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Mounts to migrate, for example media. Default: every mount that local.xml points at a remote adapter')]
        array $mounts = [],
        #[Option(description: 'Show what would be copied, and copy nothing', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Also copy the caches that Maho creates again on demand, such as the resized product images', name: 'include-cache')]
        bool $includeCache = false,
        #[Option(description: 'A folder of the mount that is not copied. Repeat it for more folders', name: 'exclude')]
        array $exclude = [],
    ): int {
        $this->initMaho();

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

            $target = MountRegistry::get($name);
            $source = MountRegistry::getLocalDefault($name);
            if ($source === null || ($target->isLocal() && realpath((string) $target->localRoot()) === realpath((string) $source->localRoot()))) {
                $io->warning("The mount \"{$name}\" uses its local folder already, so there is nothing to copy.");
                continue;
            }

            $skip = $exclude;
            if (!$includeCache) {
                $skip = array_merge($skip, Migrator::REGENERATED[$name] ?? []);
            }

            $io->section(($dryRun ? 'Dry run: ' : '') . "{$name}: {$source->localRoot()} to the configured adapter");
            if ($skip !== []) {
                $io->text('Not copied: ' . implode(', ', $skip));
            }

            $io->progressStart();
            $result = new Migrator()->migrate($source, $target, $skip, $dryRun, fn() => $io->progressAdvance());
            $io->progressFinish();

            $io->definitionList(
                [$dryRun ? 'To copy' : 'Copied' => sprintf('%d files, %s', $result->copied, $this->formatBytes($result->bytes))],
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

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        return sprintf($unit === 0 ? '%d %s' : '%.1f %s', $value, $units[$unit]);
    }
}
