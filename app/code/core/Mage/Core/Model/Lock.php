<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Named locks for mutual exclusion (cron dispatch, reindexing, order placement, ...).
 *
 * The backend is selected via <global><lock><backend> in local.xml:
 * - "file" (default): kernel flock in var/locks; released instantly when the
 *   holding process exits or crashes. Use this unless multiple servers need
 *   to share locks.
 * - "db": the adapter's advisory locks (MySQL GET_LOCK, PostgreSQL advisory
 *   locks), for multi-frontend setups sharing one database server. Do not use
 *   it on Galera-style clusters (no advisory lock support) or on SQLite (only
 *   emulated via an expiring lock table). Caveat: advisory locks belong to the
 *   DB session, so if the connection drops during a long run (e.g. wait_timeout),
 *   the server releases the lock while the holding PHP process is still running.
 *
 * Both backends share the same contract: re-entrant within the owning process,
 * a single release() frees the lock, and a blocking acquire() waits until the
 * lock is obtained. Acquired locks are tracked statically, so they stay held
 * until released or until the process ends, regardless of how this model is
 * instantiated.
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
     * the acquiring model instance is destroyed. Tracking held names is also
     * what makes both backends re-entrant with a single release(): a held name
     * never reaches the backend again (MySQL counts re-acquires, SQLite would
     * reject them).
     *
     * @var array<string, \SplFileObject|true>
     */
    protected static array $_locks = [];

    protected ?bool $_useDb = null;

    public function acquire(string $name, bool $blocking = false): bool
    {
        if (isset(self::$_locks[$name])) {
            return true;
        }

        if ($this->_useDbBackend()) {
            $connection = $this->_getConnection();
            $dbLockName = $this->_prepareDbLockName($name);
            // Retry in DB_LOCK_TIMEOUT slices so blocking waits indefinitely, like flock
            do {
                $acquired = $connection->getLock($dbLockName, $blocking ? self::DB_LOCK_TIMEOUT : 0);
            } while ($blocking && !$acquired);
            if (!$acquired) {
                return false;
            }
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

        $handle = $this->_openLockFile($name);
        if (!$handle->flock(LOCK_EX | LOCK_NB)) {
            return true;
        }
        $handle->flock(LOCK_UN);
        return false;
    }

    protected function _useDbBackend(): bool
    {
        if ($this->_useDb === null) {
            $this->_useDb = (string) Mage::getConfig()->getNode(self::XML_PATH_BACKEND) === 'db';
        }
        return $this->_useDb;
    }

    /**
     * @throws Mage_Core_Exception when the lock file cannot be created
     */
    protected function _openLockFile(string $name): SplFileObject
    {
        $lockDir = Mage::getConfig()->getVarDir('locks');
        if ($lockDir === false) {
            throw new Mage_Core_Exception('Unable to create lock directory in var/locks');
        }

        // Lock names may embed external input (e.g. payment provider order ids)
        $file = $lockDir . DS . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '.lock';
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
