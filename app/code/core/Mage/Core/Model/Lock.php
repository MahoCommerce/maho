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
 * - "file" (default): kernel flock in var/locks, the most robust option for
 *   single-server setups; released instantly when the holding process exits or crashes.
 * - "db": the adapter's advisory locks (MySQL GET_LOCK, PostgreSQL advisory locks),
 *   for multi-frontend setups sharing a single database server. Not supported on
 *   Galera-style clusters, and the SQLite implementation falls back to a lock table
 *   with expiry, so only switch when the deployment actually needs cross-server locks.
 *
 * Acquired file locks are stored statically, so they are held until released
 * or until the process ends, regardless of how this model is instantiated.
 * Like DB advisory locks, they are re-entrant within the owning process.
 */
class Mage_Core_Model_Lock
{
    public const XML_PATH_BACKEND = 'global/lock/backend';

    /**
     * Seconds to wait for a DB lock when blocking
     */
    public const DB_LOCK_TIMEOUT = 5;

    /**
     * Held file locks; static so acquired locks stay held until release or
     * process exit even if the acquiring model instance is destroyed
     *
     * @var array<string, \SplFileObject>
     */
    protected static array $_fileLocks = [];

    protected ?bool $_useDb = null;

    public function acquire(string $name, bool $blocking = false): bool
    {
        if ($this->_useDbBackend()) {
            return $this->_getConnection()->getLock($this->_prepareDbLockName($name), $blocking ? self::DB_LOCK_TIMEOUT : 0);
        }

        if (isset(self::$_fileLocks[$name])) {
            return true;
        }
        $handle = $this->_openLockFile($name);
        if (!$handle->flock($blocking ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            return false;
        }
        self::$_fileLocks[$name] = $handle;
        return true;
    }

    public function release(string $name): bool
    {
        if ($this->_useDbBackend()) {
            return $this->_getConnection()->releaseLock($this->_prepareDbLockName($name));
        }

        if (isset(self::$_fileLocks[$name])) {
            self::$_fileLocks[$name]->flock(LOCK_UN);
            unset(self::$_fileLocks[$name]);
        }
        return true;
    }

    /**
     * Whether anyone (including this process) holds the lock
     */
    public function isHeld(string $name): bool
    {
        if ($this->_useDbBackend()) {
            return $this->_getConnection()->isLocked($this->_prepareDbLockName($name));
        }

        if (isset(self::$_fileLocks[$name])) {
            return true;
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
        return ($config['dbname'] ?? '') . '.' . $name;
    }
}
