<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Lock;

/**
 * Exclusive lock backed by flock() on a file.
 *
 * The lock is owned by the kernel through the open file handle, so it is
 * released automatically when the holding process exits or crashes; stale
 * locks are impossible. Releasing also happens when this object (or its
 * last reference) is destroyed.
 */
class FileLock
{
    protected ?\SplFileObject $handle = null;

    public function __construct(protected readonly string $file) {}

    /**
     * @throws \RuntimeException when the lock file cannot be created
     */
    public function acquire(bool $blocking = false): bool
    {
        if ($this->handle !== null) {
            return true;
        }

        try {
            $handle = new \SplFileObject($this->file, 'c');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Unable to create lock file {$this->file}", 0, $e);
        }

        if (!$handle->flock($blocking ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->handle = $handle;
        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            $this->handle->flock(LOCK_UN);
            $this->handle = null;
        }
    }

    public function isAcquired(): bool
    {
        return $this->handle !== null;
    }

    /**
     * Whether anyone (including this instance) holds the lock.
     */
    public function isHeld(): bool
    {
        if ($this->handle !== null) {
            return true;
        }
        if (!$this->acquire()) {
            return true;
        }
        $this->release();
        return false;
    }
}
