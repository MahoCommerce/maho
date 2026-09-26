<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

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
 * a deep listing returns objects only, there are no locks, no seek and no
 * partial reads. Review each call site for those assumptions instead of
 * relying on the mount. Use moveAtomic() for a file that a web server or a
 * crawler can read while it is written.
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

    public function name(): string
    {
        return $this->name;
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
     * Stream the local file $sourcePath to $path on $mount.
     *
     * @throws StorageException when the local file cannot be read
     */
    public static function copyLocalFile(string $sourcePath, self $mount, string $path): void
    {
        $stream = @fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new StorageException("Cannot read the file '{$sourcePath}'.");
        }
        try {
            $mount->writeStream($path, $stream);
        } finally {
            fclose($stream);
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
