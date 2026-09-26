<?php

/**
 * One sitemap file, written to a temporary stream and put on the sitemaps mount in one step.
 *
 * A crawler can read a sitemap at any moment, so it must never see half a file: close()
 * writes the whole file with moveAtomic(), a rename on a local disk and one put on a bucket.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

final class Mage_Sitemap_Model_File
{
    public const URLSET_HEADER = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    /** @var resource */
    private $stream;

    public function __construct(
        private readonly \Maho\Storage\Mount $mount,
        private readonly string $path,
    ) {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \Maho\Storage\StorageException('Cannot open a temporary stream for the sitemap.');
        }
        $this->stream = $stream;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function write(string $data): void
    {
        fwrite($this->stream, $data);
    }

    public function close(): void
    {
        rewind($this->stream);
        try {
            $this->mount->moveAtomic($this->path, $this->stream);
        } finally {
            fclose($this->stream);
        }
    }
}
