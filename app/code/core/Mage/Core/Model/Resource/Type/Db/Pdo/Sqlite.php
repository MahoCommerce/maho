<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Type_Db_Pdo_Sqlite extends Mage_Core_Model_Resource_Type_Db
{
    /**
     * Get connection
     *
     * @param array $config Connection config
     * @return \Maho\Db\Adapter\Pdo\Sqlite
     */
    public function getConnection($config)
    {
        $configArr = (array) $config;
        $configArr['profiler'] = !empty($configArr['profiler']) && $configArr['profiler'] !== 'false';

        // SQLite uses 'path' instead of traditional host/user/password
        // Map 'dbname' to 'path' for SQLite compatibility
        if (isset($configArr['dbname']) && !isset($configArr['path'])) {
            $configArr['path'] = $configArr['dbname'];
        }

        $conn = $this->_getDbAdapterInstance($configArr);

        // SQLite doesn't need init statements like SET NAMES
        // The adapter handles initialization via PRAGMA statements

        return $conn;
    }

    /**
     * Create and return DB adapter object instance
     *
     * @param array $configArr Connection config
     * @return \Maho\Db\Adapter\Pdo\Sqlite
     */
    protected function _getDbAdapterInstance($configArr)
    {
        $className = $this->_getDbAdapterClassName();
        return new $className($configArr);
    }

    /**
     * Retrieve DB adapter class name
     *
     * @return string
     */
    protected function _getDbAdapterClassName()
    {
        return \Maho\Db\Adapter\Pdo\Sqlite::class;
    }
}
