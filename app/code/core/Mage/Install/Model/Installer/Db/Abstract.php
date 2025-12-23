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
     */
    protected ?\Maho\Db\Adapter\AdapterInterface $_connection = null;

    /**
     *  Connection configuration
     *
     * @var array<string, mixed>
     */
    protected array $_connectionData = [];

    /**
     *  Configuration data
     *
     * @var array<string, mixed>
     */
    protected array $_configData = [];

    /**
     * Return the database engine from config
     */
    public function getEngine(): string
    {
        return $this->_configData['db_engine'] ?? 'mysql';
    }

    /**
     * Return the DB type (pdo_{engine}) derived from engine
     */
    public function getType(): string
    {
        return 'pdo_' . $this->getEngine();
    }

    /**
     * Set configuration data
     */
    public function setConfig(array $config): void
    {
        $this->_configData = $config;
    }

    /**
     * Retrieve connection data from config
     */
    public function getConnectionData(): array
    {
        if (!$this->_connectionData) {
            $connectionData = [
                'host'      => $this->_configData['db_host'],
                'username'  => $this->_configData['db_user'],
                'password'  => $this->_configData['db_pass'],
                'dbname'    => $this->_configData['db_name'],
            ];
            $this->_connectionData = $connectionData;
        }
        return $this->_connectionData;
    }

    /**
     * Check InnoDB support
     */
    public function supportEngine(): bool
    {
        return true;
    }

    /**
     * Create new connection with custom config
     */
    protected function _getConnection(): \Maho\Db\Adapter\AdapterInterface
    {
        if (!isset($this->_connection)) {
            $resource   = Mage::getSingleton('core/resource');
            $connection = $resource->createConnection('install', $this->getType(), $this->getConnectionData());
            $this->_connection = $connection;
        }
        return $this->_connection;
    }

    /**
     * Retrieve required PHP extension list for database
     */
    public function getRequiredExtensions(): array
    {
        $extensions = [];
        $configExt = (array) Mage::getConfig()->getNode(sprintf('install/databases/%s/extensions', $this->getEngine()));
        foreach (array_keys($configExt) as $name) {
            $extensions[] = $name;
        }
        return $extensions;
    }
}
