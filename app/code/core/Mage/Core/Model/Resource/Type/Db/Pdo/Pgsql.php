<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * PostgreSQL PDO DB adapter resource connection type
 */
class Mage_Core_Model_Resource_Type_Db_Pdo_Pgsql extends Mage_Core_Model_Resource_Type_Db
{
    /**
     * Get connection
     *
     * @param array $config Connection config
     * @return \Maho\Db\Adapter\Pdo\Pgsql
     */
    public function getConnection($config)
    {
        $configArr = (array) $config;
        $configArr['profiler'] = !empty($configArr['profiler']) && $configArr['profiler'] !== 'false';

        $conn = $this->_getDbAdapterInstance($configArr);

        // PostgreSQL doesn't use SET NAMES like MySQL - character encoding is set via connection params
        // But we can execute other init statements if provided (excluding SET NAMES)
        if (!empty($configArr['initStatements']) && $conn) {
            $initStatements = $configArr['initStatements'];
            // Skip MySQL-specific SET NAMES statements
            if (stripos($initStatements, 'SET NAMES') === false) {
                $conn->query($initStatements);
            }
        }

        return $conn;
    }

    /**
     * Create and return DB adapter object instance
     *
     * @param array $configArr Connection config
     * @return \Maho\Db\Adapter\Pdo\Pgsql
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
        return \Maho\Db\Adapter\Pdo\Pgsql::class;
    }
}
