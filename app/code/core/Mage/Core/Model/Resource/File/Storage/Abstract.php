<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Core_Model_Resource_File_Storage_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * File storage connection name
     *
     * @var string
     */
    protected $_connectionName = null;

    /**
     * Sets name of connection the resource will use
     *
     * @param string $name
     * @return Mage_Core_Model_Resource_File_Storage_Abstract
     */
    public function setConnectionName($name)
    {
        $this->_connectionName = $name;
        return $this;
    }

    /**
     * Retrieve connection for read data
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    #[\Override]
    protected function _getReadAdapter()
    {
        return $this->_getConnection($this->_connectionName);
    }

    /**
     * Retrieve connection for write data
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    #[\Override]
    protected function _getWriteAdapter()
    {
        return $this->_getConnection($this->_connectionName);
    }

    /**
     * Get connection by name or type
     *
     * @param string $connectionName
     * @return Maho\Db\Adapter\AdapterInterface
     */
    #[\Override]
    protected function _getConnection($connectionName)
    {
        if (isset($this->_connections[$connectionName])) {
            return $this->_connections[$connectionName];
        }

        $this->_connections[$connectionName] = $this->_resources->getConnection($connectionName);

        return $this->_connections[$connectionName];
    }
}
