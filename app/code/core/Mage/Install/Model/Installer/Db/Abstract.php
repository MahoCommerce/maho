<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Install_Model_Installer_Db_Abstract
{
    /**
     *  Adapter instance
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_connection;

    /**
     *  Connection configuration
     *
     * @var array
     */
    protected $_connectionData;

    /**
     *  Connection configuration
     *
     * @var array
     */
    protected $_configData;

    /**
     * Return the database engine from config
     *
     * @return string
     */
    public function getEngine()
    {
        return $this->_configData['db_engine'];
    }

    /**
     * Return the name of DB model from config (legacy alias for getEngine)
     *
     * @return string
     * @deprecated Use getEngine() instead
     */
    public function getModel()
    {
        return $this->getEngine();
    }

    /**
     * Return the DB type from config
     *
     * @return string
     */
    public function getType()
    {
        return $this->_configData['db_type'];
    }

    /**
     * Set configuration data
     *
     * @param array $config the connection configuration
     */
    public function setConfig($config)
    {
        $this->_configData = $config;
    }

    /**
     * Retrieve connection data from config
     *
     * @return array
     */
    public function getConnectionData()
    {
        if (!$this->_connectionData) {
            $connectionData = [
                'host'      => $this->_configData['db_host'],
                'username'  => $this->_configData['db_user'],
                'password'  => $this->_configData['db_pass'],
                'dbname'    => $this->_configData['db_name'],
                'pdoType'   => $this->getPdoType(),
            ];
            $this->_connectionData = $connectionData;
        }
        return $this->_connectionData;
    }

    /**
     * Check InnoDB support
     *
     * @return bool
     */
    public function supportEngine()
    {
        return true;
    }

    /**
     * Create new connection with custom config
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getConnection()
    {
        if (!isset($this->_connection)) {
            $resource   = Mage::getSingleton('core/resource');
            $connection = $resource->createConnection('install', $this->getType(), $this->getConnectionData());
            $this->_connection = $connection;
        }
        return $this->_connection;
    }

    /**
     * Return pdo type
     */
    public function getPdoType()
    {
        return null;
    }

    /**
     * Retrieve required PHP extension list for database
     *
     * @return array
     */
    public function getRequiredExtensions()
    {
        $extensions = [];
        $configExt = (array) Mage::getConfig()->getNode(sprintf('install/databases/%s/extensions', $this->getEngine()));
        foreach (array_keys($configExt) as $name) {
            $extensions[] = $name;
        }
        return $extensions;
    }
}
