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

use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;

final class Migrator
{
    /** Folders that Maho creates again on demand, by mount. They are not copied unless asked. */
    public const REGENERATED = [
        'media' => ['catalog/product/cache', 'catalog/swatches', 'tmp'],
    ];

    /** Mounts whose local folder holds more than their files, with the step that fills the target. */
    public const NOT_COPIED = [
        'sitemaps' => 'Its local folder is public/. Generate the sitemaps again after the switch.',
    ];

    /**
     * Folders below the local folder of $name that another mount owns, such as downloadable below
     * media. The other mount copies them, so a private folder never lands in a public bucket.
     *
     * @return list<string>
     */
    public static function foldersOfOtherMounts(string $name): array
    {
        $root = MountRegistry::getLocalDefault($name)?->localRoot();
        if ($root === null) {
            return [];
        }
        $folders = [];
        foreach (MountRegistry::names() as $other) {
            $otherRoot = $other === $name ? null : MountRegistry::getLocalDefault($other)?->localRoot();
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
    public function migrate(
        Mount $source,
        Mount $target,
        array $exclude = [],
        bool $dryRun = false,
        ?callable $onFile = null,
    ): MigrationResult {
        $existing = [];
        foreach ($target->listContents('', true) as $item) {
            if ($item instanceof FileAttributes) {
                $existing[$item->path()] = $item->fileSize();
            }
        }

        $result = new MigrationResult();
        foreach ($source->listContents('', true) as $item) {
            if (!$item instanceof FileAttributes || $this->isExcluded($item->path(), $exclude)) {
                continue;
            }
            $path = $item->path();
            $size = (int) $item->fileSize();

            if (array_key_exists($path, $existing) && $existing[$path] === $size) {
                $result->skipped++;
                $onFile !== null && $onFile($path, 'skipped');
                continue;
            }

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

        return $result;
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
