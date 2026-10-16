<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

/**
 * A named Flysystem filesystem, obtained with `Mage::getStorage('media')`.
 *
 * Every Flysystem operation is available. Cloud semantics differ from a local
 * disk and are not hidden: `move()` on S3 is a server-side copy plus a delete,
 * there are no locks, no seek and no partial reads. A deep listContents()
 * returns folders on some adapters only, so use listFiles() for the files.
 * Review each call site for those assumptions instead of relying on the
 * mount. Use moveAtomic() for a file that a web server or a crawler can read
 * while it is written. Each write, move, copy and delete dispatches the event
 * storage_mount_change with the changed paths.
 */
final class Mount extends Filesystem
{
    /**
     * @param array<string, mixed> $config Flysystem defaults for every write, such as `visibility`
     */
    public function __construct(
        private readonly string $name,
        private readonly FilesystemAdapter $adapter,
        private readonly ?string $localRoot = null,
        ?PublicUrlGenerator $publicUrlGenerator = null,
        array $config = [],
    ) {
        parent::__construct($adapter, $config, null, $publicUrlGenerator);
    }

    /** False on a copy from withoutChangeEvents(). */
    private bool $dispatchesChanges = true;

    public function name(): string
    {
        return $this->name;
    }

    /**
     * This mount without the storage_mount_change event, for a copy of files that do not change,
     * such as storage:migrate.
     */
    public function withoutChangeEvents(): self
    {
        $mount = clone $this;
        $mount->dispatchesChanges = false;
        return $mount;
    }

    #[\Override]
    public function write(string $location, string $contents, array $config = []): void
    {
        parent::write($location, $contents, $config);
        $this->dispatchChange([$location]);
    }

    #[\Override]
    public function writeStream(string $location, $contents, array $config = []): void
    {
        parent::writeStream($location, $contents, $config);
        $this->dispatchChange([$location]);
    }

    #[\Override]
    public function move(string $source, string $destination, array $config = []): void
    {
        parent::move($source, $destination, $config);
        $this->dispatchChange([$source, $destination]);
    }

    #[\Override]
    public function copy(string $source, string $destination, array $config = []): void
    {
        parent::copy($source, $destination, $config);
        $this->dispatchChange([$destination]);
    }

    #[\Override]
    public function delete(string $location): void
    {
        parent::delete($location);
        $this->dispatchChange([$location]);
    }

    #[\Override]
    public function deleteDirectory(string $location): void
    {
        parent::deleteDirectory($location);
        $this->dispatchChange([], [$location]);
    }

    public function isLocal(): bool
    {
        return $this->adapter instanceof LocalFilesystemAdapter;
    }

    /** The absolute directory behind a local mount. Null for a remote adapter. */
    public function localRoot(): ?string
    {
        return $this->isLocal() ? $this->localRoot : null;
    }

    /** True when temporaryUrl() works, for example on S3. A local disk has no signed URLs. */
    public function supportsTemporaryUrls(): bool
    {
        return $this->adapter instanceof TemporaryUrlGenerator;
    }

    /**
     * The files below $path at any depth, with no folder, on every adapter.
     *
     * @return DirectoryListing<FileAttributes>
     */
    public function listFiles(string $path = ''): DirectoryListing
    {
        $files = function () use ($path): \Generator {
            foreach ($this->listContents($path, true) as $item) {
                if ($item instanceof FileAttributes) {
                    yield $item;
                }
            }
        };

        return new DirectoryListing($files());
    }

    /**
     * Writes to a temp key and moves it into place, so a concurrent reader
     * (the web server, a crawler, another node) never sees a partial file.
     *
     * Local: the temp key sits next to the target and the move is a rename,
     * which is atomic on one disk. Remote: a plain write, because one PutObject
     * is already atomic, while a temp key plus move() on S3 is a copy plus a
     * delete, slower and not atomic.
     *
     * @param string|resource $contents
     * @param array<string, mixed> $config
     */
    public function moveAtomic(string $path, mixed $contents, array $config = []): void
    {
        if (!$this->isLocal()) {
            $this->writeAny($path, $contents, $config);
            return;
        }

        $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            $this->writeAny($temp, $contents, $config);
            $this->move($temp, $path, $config);
        } catch (\Throwable $e) {
            try {
                $this->delete($temp);
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    /**
     * Stream the local file $sourcePath to $path on this mount.
     *
     * @throws StorageException when the local file cannot be read
     */
    public function copyFromLocalFile(string $sourcePath, string $path): void
    {
        $stream = @fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new StorageException("Cannot read the file '{$sourcePath}'.");
        }
        try {
            $this->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Stream the file $path of this mount to the local file $targetPath, and create its folder when it is missing.
     *
     * @throws StorageException when the local file cannot be written
     */
    public function copyToLocalFile(string $path, string $targetPath): void
    {
        $directory = dirname($targetPath);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new StorageException("Cannot create the folder '{$directory}'.");
        }
        $source = $this->readStream($path);
        try {
            $target = @fopen($targetPath, 'wb');
            if ($target === false) {
                throw new StorageException("Cannot write the file '{$targetPath}'.");
            }
            try {
                if (stream_copy_to_stream($source, $target) === false) {
                    throw new StorageException("Cannot write the file '{$targetPath}'.");
                }
            } finally {
                fclose($target);
            }
        } finally {
            fclose($source);
        }
    }

    /**
     * Call $callback with a local path that holds the file $path. A local mount gives its own file.
     * A remote mount gives a temp copy, and this method deletes the copy after the call.
     *
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    public function withLocalFile(string $path, callable $callback): mixed
    {
        $root = $this->localRoot();
        if ($root !== null) {
            return $callback($root . '/' . $path);
        }

        $localPath = tempnam(sys_get_temp_dir(), 'maho_mount_');
        if ($localPath === false) {
            throw new StorageException('Cannot create a temp file.');
        }
        try {
            $this->copyToLocalFile($path, $localPath);
            return $callback($localPath);
        } finally {
            @unlink($localPath);
        }
    }

    /**
     * Tell the modules which files and folders an operation changed, so they can delete the data
     * that they keep about them, such as the size of an image.
     *
     * @param list<string> $paths
     * @param list<string> $folders
     */
    private function dispatchChange(array $paths, array $folders = []): void
    {
        if ($this->dispatchesChanges) {
            \Mage::dispatchEvent('storage_mount_change', ['mount' => $this, 'paths' => $paths, 'folders' => $folders]);
        }
    }

    /**
     * @param string|resource $contents
     * @param array<string, mixed> $config
     */
    private function writeAny(string $path, mixed $contents, array $config): void
    {
        if (is_resource($contents)) {
            $this->writeStream($path, $contents, $config);
        } else {
            $this->write($path, (string) $contents, $config);
        }
    }
}
