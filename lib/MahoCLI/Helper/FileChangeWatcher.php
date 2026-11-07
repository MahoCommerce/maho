<?php

/**
 * Tells when a set of files changed and then stayed the same for a short time.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

class FileChangeWatcher
{
    /** @var array<string, string|false> */
    private array $hashes;

    private ?float $changedAt = null;

    /**
     * @param list<string> $files
     */
    public function __construct(
        private readonly array $files,
        private readonly float $settleSeconds = 0.3,
    ) {
        $this->hashes = $this->readHashes();
    }

    /**
     * Count a change at $now, so poll() gives true after the settle time even when no file changes.
     */
    public function markChanged(float $now): void
    {
        $this->changedAt = $now;
    }

    /**
     * Whether a file changed, and no file changed in the $settleSeconds before $now. It gives
     * true one time for each group of changes.
     */
    public function poll(float $now): bool
    {
        // A hash, because the time of a file has a resolution of one second
        $hashes = $this->readHashes();
        if ($hashes !== $this->hashes) {
            $this->hashes = $hashes;
            $this->changedAt = $now;
            return false;
        }
        if ($this->changedAt !== null && $now - $this->changedAt >= $this->settleSeconds) {
            $this->changedAt = null;
            return true;
        }
        return false;
    }

    /**
     * @return array<string, string|false>
     */
    private function readHashes(): array
    {
        $hashes = [];
        foreach ($this->files as $file) {
            $hashes[$file] = is_file($file) ? hash_file('xxh128', $file) : false;
        }
        return $hashes;
    }
}
