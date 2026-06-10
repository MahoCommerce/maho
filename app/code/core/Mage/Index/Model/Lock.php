<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Lock
{
    /**
     * Seconds to wait for a DB lock when blocking
     */
    public const DB_LOCK_TIMEOUT = 5;

    /**
     * Singleton instance
     *
     * @var Mage_Index_Model_Lock|null
     */
    protected static $_instance;

    /**
     * File lock instances, kept alive so locks stay held for the process lifetime
     *
     * @var array<string, \Maho\Lock\FileLock>
     */
    protected static $_fileLocks = [];

    protected function __construct() {}

    /**
     * Get lock singleton instance
     *
     * @return Mage_Index_Model_Lock
     */
    public static function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Set named lock
     *
     * @param string $lockName
     * @param bool $file use a file lock (single server) instead of a DB lock
     * @param bool $block wait for the lock instead of failing fast
     * @return bool
     */
    public function setLock($lockName, $file = false, $block = false)
    {
        if ($file) {
            return $this->_getFileLock($lockName)->acquire($block);
        }
        return $this->_getConnection()->getLock($this->_prepareDbLockName($lockName), $block ? self::DB_LOCK_TIMEOUT : 0);
    }

    /**
     * Release named lock by name
     *
     * @param string $lockName
     * @param bool $file
     * @return bool
     */
    public function releaseLock($lockName, $file = false)
    {
        if ($file) {
            $this->_getFileLock($lockName)->release();
            return true;
        }
        return $this->_getConnection()->releaseLock($this->_prepareDbLockName($lockName));
    }

    /**
     * Check whether the named lock exists
     *
     * @param string $lockName
     * @param bool $file
     * @return bool
     */
    public function isLockExists($lockName, $file = false)
    {
        if ($file) {
            return $this->_getFileLock($lockName)->isHeld();
        }
        return $this->_getConnection()->isLocked($this->_prepareDbLockName($lockName));
    }

    protected function _getFileLock(string $lockName): \Maho\Lock\FileLock
    {
        if (!isset(self::$_fileLocks[$lockName])) {
            $lockDir = Mage::getConfig()->getVarDir('locks');
            if ($lockDir === false) {
                throw new Mage_Core_Exception('Unable to create lock directory in var/locks');
            }
            self::$_fileLocks[$lockName] = new \Maho\Lock\FileLock($lockDir . DS . $lockName . '.lock');
        }
        return self::$_fileLocks[$lockName];
    }

    protected function _getConnection(): \Maho\Db\Adapter\AdapterInterface
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Prefix with the DB name: advisory locks are server-global on MySQL
     */
    protected function _prepareDbLockName(string $lockName): string
    {
        $config = $this->_getConnection()->getConfig();
        return ($config['dbname'] ?? '') . '.' . $lockName;
    }
}
