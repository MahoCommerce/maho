<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Resource_Helper_Sqlite extends Mage_Core_Model_Resource_Helper_Sqlite implements Mage_Index_Model_Resource_Helper_Lock_Interface
{
    /**
     * Insert data from select statement
     *
     * @param Mage_Index_Model_Resource_Abstract $object
     * @param Maho\Db\Select $select
     * @param string $destTable
     * @param array $columns
     * @param bool $readToIndex
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function insertData($object, $select, $destTable, $columns, $readToIndex)
    {
        return $object->insertFromSelect($select, $destTable, $columns, $readToIndex);
    }

    /**
     * Set lock
     *
     * @param string $name
     * @return bool
     */
    #[\Override]
    public function setLock($name)
    {
        return $this->_getWriteAdapter()->getLock($name, self::LOCK_GET_TIMEOUT);
    }

    /**
     * Release lock
     *
     * @param string $name
     * @return bool
     */
    #[\Override]
    public function releaseLock($name)
    {
        return $this->_getWriteAdapter()->releaseLock($name);
    }

    /**
     * Is lock exists
     *
     * @param string $name
     * @return bool
     */
    #[\Override]
    public function isLocked($name)
    {
        return $this->_getWriteAdapter()->isLocked($name);
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setWriteAdapter(Maho\Db\Adapter\AdapterInterface $adapter)
    {
        $this->_writeAdapter = $adapter;

        return $this;
    }
}
