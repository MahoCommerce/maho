<?php

/**
 * The width and the height of image files on the mounts, kept in core_image_size.
 *
 * A theme ratio of "original" needs the size of each source image to write the height of an img
 * element. A page never reads a file on a bucket for it: the resizer stores the size when it
 * decodes a source, and a missing row is read by the queue. A mount deletes the row of each file
 * that it writes, moves or deletes, through the storage_mount_change event.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use League\Flysystem\WhitespacePathNormalizer;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

class Mage_Core_Model_Image_Size
{
    /** Paths in one queue message. */
    public const BATCH_SIZE = 100;

    /** Rows that one query reads when all rows are read. */
    public const READ_BATCH_SIZE = 5000;

    /** @var array<string, array{0: int, 1: int}|false> the sizes that this request knows, by hash. False: no size */
    protected array $sizes = [];

    /**
     * The key of the row of $path on the mount $mount. The md5 keeps two names that differ only in
     * case apart: MySQL compares text without case.
     */
    public static function getHash(string $mount, string $path): string
    {
        return md5($mount . '/' . self::normalizePath($path));
    }

    public static function normalizePath(string $path): string
    {
        return new WhitespacePathNormalizer()->normalizePath($path);
    }

    /**
     * The width and the height of the image $path on $mount. A file on a local disk is read and
     * stored when it has no row. A file on a bucket is not read: the queue reads it, and until
     * then this returns null.
     *
     * @return array{0: int, 1: int}|null null when the size is not known or the file is not an image
     */
    public function get(Mount $mount, string $path): ?array
    {
        $path = self::normalizePath($path);
        $hash = self::getHash($mount->name(), $path);
        if (!array_key_exists($hash, $this->sizes)) {
            $size = $this->getResource()->fetchSize($hash);
            if ($size === null && $mount->isLocal()) {
                $size = $this->read($mount, $path);
            } elseif ($size === null) {
                $this->queue($mount->name(), [$path]);
            }
            $this->sizes[$hash] = $size === null || $size[0] < 1 || $size[1] < 1 ? false : $size;
        }
        return $this->sizes[$hash] ?: null;
    }

    /**
     * Read the stored sizes of $files of the image entity $entity with one query, so a listing
     * does not query each of its images. get() then finds them in the memory of the request.
     *
     * @param iterable<mixed> $files paths below the source folder of $entity. Other values are skipped
     */
    public function preload(string $entity, iterable $files): void
    {
        $source = Mage::getSingleton('core/image_config')->getSource($entity);
        $mount = Mage::getStorage('media')->name();
        $hashes = [];
        foreach ($files as $file) {
            $sourcePath = is_string($file) && $file !== '' ? Mage_Core_Model_Image_Set::resolveSourcePath($source, $file) : null;
            if ($sourcePath !== null && !array_key_exists($hash = self::getHash($mount, $sourcePath), $this->sizes)) {
                $hashes[$hash] = true;
            }
        }
        if ($hashes === []) {
            return;
        }
        foreach ($this->getResource()->fetchSizes(array_map(strval(...), array_keys($hashes))) as $hash => $size) {
            $this->sizes[$hash] = $size[0] > 0 && $size[1] > 0 ? $size : false;
        }
    }

    /**
     * Read the size of $path from $mount and store it. A missing file, or a file that is not an
     * image, gets a row of 0 x 0, so a page does not ask for it again.
     *
     * @return array{0: int, 1: int}|null null when the file is missing or is not an image
     * @throws \League\Flysystem\FilesystemException when the mount cannot read the file
     */
    public function read(Mount $mount, string $path): ?array
    {
        $info = $mount->fileExists($path) ? \Maho\Io::readImageSizeOnMount($mount, $path) : false;
        $width = $info === false ? 0 : (int) $info[0];
        $height = $info === false ? 0 : (int) $info[1];
        $this->save($mount->name(), $path, $width, $height);
        return $width > 0 && $height > 0 ? [$width, $height] : null;
    }

    public function save(string $mount, string $path, int $width, int $height): void
    {
        $path = self::normalizePath($path);
        $hash = self::getHash($mount, $path);
        $this->getResource()->saveSize($hash, $mount, $path, $width, $height);
        $this->sizes[$hash] = $width > 0 && $height > 0 ? [$width, $height] : false;
    }

    /**
     * Delete the rows of the image files $paths and of every file below $folders on $mount. The
     * resized files of the image entities have no rows, so their folders are skipped.
     *
     * @param list<string> $paths
     * @param list<string> $folders
     */
    public function forget(string $mount, array $paths, array $folders = []): void
    {
        $hashes = [];
        foreach ($paths as $path) {
            $path = self::normalizePath($path);
            if ($this->canHaveSize($mount, $path)) {
                $hashes[] = $hash = self::getHash($mount, $path);
                unset($this->sizes[$hash]);
            }
        }
        if ($hashes !== []) {
            $this->getResource()->deleteHashes($hashes);
        }

        foreach ($folders as $folder) {
            $folder = self::normalizePath($folder);
            if (!$this->isResized($mount, $folder)) {
                $this->getResource()->deleteFolder($mount, $folder);
                $this->sizes = [];
            }
        }
    }

    /**
     * Whether $path on the mount $mount is an image that can have a stored size. The resized
     * files of the image entities have none.
     */
    protected function canHaveSize(string $mount, string $path): bool
    {
        return !$this->isResized($mount, $path)
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [...\Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS, 'svg'], true);
    }

    /**
     * Whether $path on the mount $mount is a resized file or folder of an image entity.
     */
    protected function isResized(string $mount, string $path): bool
    {
        if ($mount !== 'media') {
            return false;
        }
        foreach (Mage::getSingleton('core/image_config')->getEntities() as $source) {
            if (str_starts_with($path . '/', $source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Queue the read of the sizes of $paths on the mount $mount. A page only needs the size for
     * the next render, so an error is logged and does not stop the caller.
     *
     * @param list<string> $paths
     */
    public function queue(string $mount, array $paths): void
    {
        try {
            foreach (array_chunk($paths, self::BATCH_SIZE) as $batch) {
                \Maho\Queue\QueueManager::dispatch(
                    new Mage_Core_Model_Image_SizeMessage($mount, $batch),
                    queue: Mage_Core_Model_Image_Resizer::QUEUE_NAME,
                    dedupeKey: count($batch) === 1 ? 'image_size_' . self::getHash($mount, $batch[0]) : null,
                );
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Queue a new read of the size of every row. The rows stay until the queue replaces them, so
     * a page keeps its sizes in the meantime. This repairs the sizes after the files changed
     * outside Maho.
     */
    public function refreshAll(): void
    {
        $after = '';
        while (($rows = $this->getResource()->fetchBatch($after, self::READ_BATCH_SIZE)) !== []) {
            $paths = [];
            foreach ($rows as $row) {
                $paths[$row['mount']][] = $row['path'];
            }
            foreach ($paths as $mount => $list) {
                $this->queue((string) $mount, $list);
            }
            $after = (string) end($rows)['path_hash'];
        }
    }

    /**
     * Read and store the size of each of $paths on the mount $mount. The row of a mount that no
     * longer exists is deleted.
     *
     * @param list<string> $paths
     * @param (callable(string $path, \League\Flysystem\FilesystemException $e): void)|null $onFailure
     *        called for each file that the mount cannot read
     * @param (callable(string $path): void)|null $onFile called after each file
     */
    public function readAll(string $mount, array $paths, ?callable $onFailure = null, ?callable $onFile = null): void
    {
        if (!MountRegistry::has($mount)) {
            $this->getResource()->deleteHashes(array_map(fn(string $path): string => self::getHash($mount, $path), $paths));
            return;
        }
        $storage = Mage::getStorage($mount);
        foreach ($paths as $path) {
            try {
                $this->read($storage, $path);
            } catch (\League\Flysystem\FilesystemException $e) {
                $onFailure !== null && $onFailure($path, $e);
            }
            $onFile !== null && $onFile($path);
        }
    }

    /**
     * The source files of $files of the image entity $entity that have no stored size.
     *
     * @param iterable<string> $files paths below the source folder of $entity
     * @return list<string> paths on the media mount
     */
    public function filterUnknown(string $entity, iterable $files): array
    {
        $source = Mage::getSingleton('core/image_config')->getSource($entity);
        $paths = [];
        foreach ($files as $file) {
            $sourcePath = Mage_Core_Model_Image_Set::resolveSourcePath($source, (string) $file);
            if ($sourcePath !== null) {
                $paths[self::getHash('media', $sourcePath)] = $sourcePath;
            }
        }
        $existing = $this->getResource()->fetchSizes(array_map(strval(...), array_keys($paths)));
        return array_values(array_diff_key($paths, $existing));
    }

    /**
     * Delete the rows of the files in the source folders of the image entities that no record
     * of an entity uses. The placeholders keep their rows.
     *
     * @return int the number of deleted rows
     */
    public function deleteOrphans(): int
    {
        $resizer = Mage::getSingleton('core/image_resizer');
        $sources = [];
        $keep = [];
        foreach (Mage::getSingleton('core/image_config')->getEntities() as $entity => $source) {
            $sources[] = $source . '/';
            foreach ($resizer->getSourceFiles($entity) as $file) {
                $sourcePath = Mage_Core_Model_Image_Set::resolveSourcePath($source, (string) $file);
                if ($sourcePath !== null) {
                    $keep[self::getHash('media', $sourcePath)] = true;
                }
            }
        }

        $count = 0;
        $after = '';
        while (($rows = $this->getResource()->fetchBatch($after, self::READ_BATCH_SIZE)) !== []) {
            $orphans = [];
            foreach ($rows as $row) {
                if ($row['mount'] === 'media' && !isset($keep[$row['path_hash']]) && $this->isOrphanPath($row['path'], $sources)) {
                    $orphans[] = $row['path_hash'];
                }
            }
            $this->getResource()->deleteHashes($orphans);
            $count += count($orphans);
            $after = (string) end($rows)['path_hash'];
        }
        $this->sizes = [];
        return $count;
    }

    /**
     * @param list<string> $sources the source folders of the entities, each with a slash at the end
     */
    protected function isOrphanPath(string $path, array $sources): bool
    {
        foreach ($sources as $source) {
            if (str_starts_with($path, $source)) {
                return !str_starts_with($path, $source . Mage_Core_Model_Image_Resizer::PLACEHOLDER_FOLDER . '/');
            }
        }
        return false;
    }

    protected function getResource(): Mage_Core_Model_Resource_Image_Size
    {
        return Mage::getResourceSingleton('core/image_size');
    }
}
