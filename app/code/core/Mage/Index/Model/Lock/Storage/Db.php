<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Lock_Storage_Db implements Mage_Index_Model_Lock_Storage_Interface
{
    /**
     * @var Mage_Index_Model_Resource_Helper_Lock_Interface
     */
    protected $_helper;

    /**
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_connection;

    public function __construct()
    {
        /** @var Mage_Index_Model_Resource_Lock_Resource $resource */
        $resource = Mage::getSingleton('index/resource_lock_resource');
        $this->_connection = $resource->getConnection('index_write', 'default_lock');
        $helper = Mage::getResourceHelper('index');
        if (!$helper instanceof Mage_Index_Model_Resource_Helper_Lock_Interface) {
            throw new Mage_Core_Exception('Index resource helper must implement Lock interface');
        }
        $this->_helper = $helper;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function _prepareLockName($name)
    {
        $config = $this->_connection->getConfig();
        return $config['dbname'] . '.' . $name;
    }

    /**
     * Set named lock
     *
     * @param string $lockName
     * @return bool
     */
    #[\Override]
    public function setLock($lockName)
    {
        $lockName = $this->_prepareLockName($lockName);
        return $this->_helper->setLock($lockName);
    }

    /**
     * Release named lock
     *
     * @param string $lockName
     * @return bool
     */
    #[\Override]
    public function releaseLock($lockName)
    {
        $lockName = $this->_prepareLockName($lockName);
        return $this->_helper->releaseLock($lockName);
    }

    /**
     * Check whether the lock exists
     *
     * @param string $lockName
     * @return bool
     */
    #[\Override]
    public function isLockExists($lockName)
    {
        $lockName = $this->_prepareLockName($lockName);
        return $this->_helper->isLocked($lockName);
    }
}
