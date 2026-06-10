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
 * Use via Mage::getSingleton('core/lock'): the singleton keeps file locks alive,
 * so an acquired lock is held until released or until the process ends.
 */
class Mage_Core_Model_Lock
{
    public const XML_PATH_BACKEND = 'global/lock/backend';

    /**
     * Seconds to wait for a DB lock when blocking
     */
    public const DB_LOCK_TIMEOUT = 5;

    /**
     * File lock instances, kept alive so locks stay held until release or process exit
     *
     * @var array<string, \Maho\Lock\FileLock>
     */
    protected array $_fileLocks = [];

    protected ?bool $_useDb = null;

    public function acquire(string $name, bool $blocking = false): bool
    {
        if ($this->_useDbBackend()) {
            return $this->_getConnection()->getLock($this->_prepareDbLockName($name), $blocking ? self::DB_LOCK_TIMEOUT : 0);
        }
        return $this->_getFileLock($name)->acquire($blocking);
    }

    public function release(string $name): bool
    {
        if ($this->_useDbBackend()) {
            return $this->_getConnection()->releaseLock($this->_prepareDbLockName($name));
        }
        $this->_getFileLock($name)->release();
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
        return $this->_getFileLock($name)->isHeld();
    }

    protected function _useDbBackend(): bool
    {
        if ($this->_useDb === null) {
            $this->_useDb = (string) Mage::getConfig()->getNode(self::XML_PATH_BACKEND) === 'db';
        }
        return $this->_useDb;
    }

    protected function _getFileLock(string $name): \Maho\Lock\FileLock
    {
        if (!isset($this->_fileLocks[$name])) {
            $lockDir = Mage::getConfig()->getVarDir('locks');
            if ($lockDir === false) {
                throw new Mage_Core_Exception('Unable to create lock directory in var/locks');
            }
            $this->_fileLocks[$name] = new \Maho\Lock\FileLock($lockDir . DS . $name . '.lock');
        }
        return $this->_fileLocks[$name];
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
