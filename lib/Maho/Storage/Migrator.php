<?php

/**
 * Copies the files of one mount to another, for example a local media folder to a bucket.
 *
 * The target is listed once, and a file that is there already with the same size is skipped.
 * So a second run after an interruption continues where the first one stopped, with no
 * request per file. The source is never changed: delete it only after a check of the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\FilesystemException;

final class Migrator
{
    /** Folders that Maho creates again on demand, by mount. They are not copied unless asked. */
    public const REGENERATED = [
        'media' => ['catalog/product/cache', 'catalog/swatches', 'tmp'],
    ];

    /** Mounts whose local folder holds more than their files, with the step that fills the target. */
    public const NOT_COPIED = [
        'sitemaps' => 'Its local folder is public/. Generate the sitemaps again after the switch, then delete the old sitemap files from public/.',
    ];

    /**
     * Folders below the local folder of $name that another mount owns, such as downloadable below
     * media. The other mount copies them, so a private folder never lands in a public bucket.
     *
     * @return list<string>
     */
    public static function foldersOfOtherMounts(string $name): array
    {
        $root = MountRegistry::getDeclaredLocalMount($name)?->localRoot();
        if ($root === null) {
            return [];
        }
        $folders = [];
        foreach (MountRegistry::names() as $other) {
            $otherRoot = $other === $name ? null : MountRegistry::getDeclaredLocalMount($other)?->localRoot();
            if ($otherRoot !== null && str_starts_with($otherRoot, $root . '/')) {
                $folders[] = substr($otherRoot, strlen($root) + 1);
            }
        }
        return $folders;
    }

    /**
     * @param list<string> $exclude folders of the source that are not copied
     * @param (callable(string $path, string $action): void)|null $onFile called after each file,
     *        with the action "copied", "skipped" or "failed"
     */
    public function migrate(Mount $source, Mount $target, array $exclude = [], bool $dryRun = false, ?callable $onFile = null): MigrationResult
    {
        $result = new MigrationResult();
        $existing = $this->listFiles($target, $exclude);
        $files = $this->findMissingFiles($source, $existing, $exclude, $result, $onFile);
        $this->copyFiles($source, $target, $files, $dryRun, $result, $onFile);
        return $result;
    }

    /**
     * List the files of $mount with one deep listing.
     *
     * @param list<string> $exclude
     * @param (callable(string $path): void)|null $onFile called for each listed file
     * @return array<string, int> the size of each file, by path
     */
    public function listFiles(Mount $mount, array $exclude = [], ?callable $onFile = null): array
    {
        $files = [];
        foreach ($mount->listFiles() as $item) {
            if (!$this->isExcluded($item->path(), $exclude)) {
                $files[$item->path()] = (int) $item->fileSize();
                $onFile !== null && $onFile($item->path());
            }
        }
        return $files;
    }

    /**
     * Find the files of $source that $existing does not hold with the same size. Each file that
     * is there already adds to $result->skipped.
     *
     * @param array<string, int> $existing the size of each file of the target, by path, from listFiles()
     * @param list<string> $exclude
     * @param (callable(string $path, string $action): void)|null $onFile called for each skipped file
     * @return array<string, int> the size of each missing file, by path
     */
    public function findMissingFiles(Mount $source, array $existing, array $exclude, MigrationResult $result, ?callable $onFile = null): array
    {
        $missing = [];
        foreach ($this->listFiles($source, $exclude) as $path => $size) {
            $path = (string) $path;
            if (($existing[$path] ?? null) === $size) {
                $result->skipped++;
                $onFile !== null && $onFile($path, 'skipped');
                continue;
            }
            $missing[$path] = $size;
        }

        return $missing;
    }

    /**
     * Copy each file of $files from $source to $target, and add the counts to $result.
     *
     * @param array<string, int> $files the size of each file, by path
     * @param (callable(string $path, string $action): void)|null $onFile called after each file,
     *        with the action "copied" or "failed"
     */
    public function copyFiles(Mount $source, Mount $target, array $files, bool $dryRun, MigrationResult $result, ?callable $onFile = null): void
    {
        foreach ($files as $path => $size) {
            $path = (string) $path;
            try {
                if (!$dryRun) {
                    $stream = $source->readStream($path);
                    try {
                        $target->writeStream($path, $stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
                $result->copied++;
                $result->bytes += $size;
                $onFile !== null && $onFile($path, 'copied');
            } catch (FilesystemException $e) {
                $result->failed[$path] = $e->getMessage();
                $onFile !== null && $onFile($path, 'failed');
            }
        }
    }

    /**
     * The number of files that migrate() looks at, to size a progress bar.
     *
     * @param list<string> $exclude
     */
    public function countFiles(Mount $source, array $exclude = []): int
    {
        return count($this->listFiles($source, $exclude));
    }

    /**
     * @param list<string> $exclude
     */
    private function isExcluded(string $path, array $exclude): bool
    {
        foreach ($exclude as $folder) {
            $folder = trim($folder, '/');
            if ($folder !== '' && ($path === $folder || str_starts_with($path, $folder . '/'))) {
                return true;
            }
        }
        return false;
    }
}
