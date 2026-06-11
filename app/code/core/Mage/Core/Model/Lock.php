<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * Named locks for mutual exclusion (cron dispatch, reindexing, order placement, ...).
 *
 * The backend ("file" default, or "db") is selected via <global><lock><backend>;
 * see local.xml.template for when to use each and their caveats.
 *
 * Both backends share the same contract: a lock this process already holds
 * cannot be taken again, so a second acquire() returns false just as it would
 * for a separate process; release() frees it. A blocking acquire() waits until
 * the lock is obtained. Acquired locks are tracked statically, so they stay
 * held until released or until the process ends, regardless of how this model
 * is instantiated.
 */
class Mage_Core_Model_Lock
{
    public const XML_PATH_BACKEND = 'global/lock/backend';

    /**
     * Seconds per acquisition attempt when blocking on the db backend
     */
    public const DB_LOCK_TIMEOUT = 5;

    /**
     * Held locks (SplFileObject for the file backend, true for the db backend);
     * static so acquired locks stay held until release or process exit even if
     * the acquiring model instance is destroyed. Tracking held names also keeps
     * the backends consistent: a name we already hold is reported as taken
     * without reaching the backend, where MySQL would count the re-acquire and
     * SQLite would error on it.
     *
     * @var array<string, \SplFileObject|true>
     */
    protected static array $_locks = [];

    protected ?bool $_useDb = null;

    public function acquire(string $name, bool $blocking = false): bool
    {
        // Already held by this process: not re-entrant, fail like a separate process would
        if (isset(self::$_locks[$name])) {
            return false;
        }

        if ($this->_useDbBackend()) {
            $connection = $this->_getConnection();
            $dbLockName = $this->_prepareDbLockName($name);
            if (!$blocking) {
                if (!$connection->getLock($dbLockName, 0)) {
                    return false;
                }
                self::$_locks[$name] = true;
                return true;
            }
            // Retry in DB_LOCK_TIMEOUT slices so blocking waits indefinitely, like flock.
            // A contended GET_LOCK blocks for the full timeout before returning false; an
            // error (GET_LOCK returns NULL, not 0) returns near-instantly. Treat a fast
            // failure as an error and abort, otherwise we would spin tightly forever.
            do {
                $startedAt = microtime(true);
                $acquired = $connection->getLock($dbLockName, self::DB_LOCK_TIMEOUT);
                if (!$acquired && microtime(true) - $startedAt < self::DB_LOCK_TIMEOUT - 1) {
                    return false;
                }
            } while (!$acquired);
            self::$_locks[$name] = true;
            return true;
        }

        $handle = $this->_openLockFile($name);
        if (!$handle->flock($blocking ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            return false;
        }
        self::$_locks[$name] = $handle;
        return true;
    }

    public function release(string $name): bool
    {
        if (!isset(self::$_locks[$name])) {
            return true;
        }

        $lock = self::$_locks[$name];
        unset(self::$_locks[$name]);
        if ($lock instanceof SplFileObject) {
            $lock->flock(LOCK_UN);
        } else {
            $this->_getConnection()->releaseLock($this->_prepareDbLockName($name));
        }
        return true;
    }

    /**
     * Whether anyone (including this process) holds the lock
     */
    public function isHeld(string $name): bool
    {
        if (isset(self::$_locks[$name])) {
            return true;
        }

        if ($this->_useDbBackend()) {
            return $this->_getConnection()->isLocked($this->_prepareDbLockName($name));
        }

        // No file means nobody has ever taken this lock; don't create one just to probe
        if (!is_file($this->_lockFilePath($name))) {
            return false;
        }

        $handle = $this->_openLockFile($name);
        if (!$handle->flock(LOCK_EX | LOCK_NB)) {
            return true;
        }
        $handle->flock(LOCK_UN);
        return false;
    }

    /**
     * Remove stale lock files left behind by short-lived names (notably the
     * per-order paypal_order_<id> locks, one of which is created per checkout).
     * release() never unlinks, since unlinking a live lock would let two
     * processes hold the same name; only files older than $olderThanSeconds
     * that nobody currently holds are removed, so there is no live lock to race.
     * Returns the number of files removed. Operates on the filesystem regardless
     * of the configured backend, so leftovers from a prior file-backend run are
     * still reclaimed after a switch to db.
     */
    public function cleanupStaleLockFiles(int $olderThanSeconds = 86400): int
    {
        // Resolve without getVarDir(): a cleanup pass must not create the directory
        // it is meant to clean, and a db-backend host may not even have one.
        $lockDir = Mage::getBaseDir('var') . DS . 'locks';
        if (!is_dir($lockDir)) {
            return 0;
        }

        $cutoff = time() - $olderThanSeconds;
        $removed = 0;
        foreach (glob($lockDir . DS . '*.lock') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime > $cutoff) {
                continue;
            }
            $handle = @fopen($file, 'c');
            if ($handle === false) {
                continue;
            }
            // Only remove a lock nobody holds, so no process is parked on it to orphan
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                if (@unlink($file)) {
                    $removed++;
                }
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
        return $removed;
    }

    protected function _useDbBackend(): bool
    {
        if ($this->_useDb === null) {
            $this->_useDb = (string) Mage::getConfig()->getNode(self::XML_PATH_BACKEND) === 'db';
        }
        return $this->_useDb;
    }

    /**
     * @throws Mage_Core_Exception when the lock directory cannot be created
     */
    protected function _lockFilePath(string $name): string
    {
        $lockDir = Mage::getConfig()->getVarDir('locks');
        if ($lockDir === false) {
            throw new Mage_Core_Exception('Unable to create lock directory in var/locks');
        }

        // Lock names may embed external input (e.g. payment provider order ids)
        return $lockDir . DS . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '.lock';
    }

    /**
     * @throws Mage_Core_Exception when the lock file cannot be created
     */
    protected function _openLockFile(string $name): SplFileObject
    {
        $file = $this->_lockFilePath($name);
        try {
            return new SplFileObject($file, 'c');
        } catch (RuntimeException $e) {
            throw new Mage_Core_Exception("Unable to create lock file {$file}", 0, $e);
        }
    }

    protected function _getConnection(): \Maho\Db\Adapter\AdapterInterface
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Prefix with the DB name: advisory locks are server-global on MySQL
     */
    protected function _prepareDbLockName(string $name): string
    {
        $config = $this->_getConnection()->getConfig();
        $lockName = ($config['dbname'] ?? '') . '.' . $name;
        // MySQL rejects user-level lock names longer than 64 characters
        return strlen($lockName) > 64 ? md5($lockName) : $lockName;
    }
}
