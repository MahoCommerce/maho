<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Type_Db_Pdo_Mysql extends Mage_Core_Model_Resource_Type_Db
{
    /**
     * @param array $config Connection config
     * @return Maho\Db\Adapter\Pdo\Mysql
     */
    public function getConnection($config)
    {
        $configArr = (array) $config;
        $configArr['profiler'] = !empty($configArr['profiler']) && $configArr['profiler'] !== 'false';

        $conn = $this->_getDbAdapterInstance($configArr);

        if (!empty($configArr['initStatements']) && $conn) {
            $conn->query($configArr['initStatements']);
        }

        return $conn;
    }

    /**
     * Create and return DB adapter object instance
     *
     * @param array $configArr Connection config
     * @return Maho\Db\Adapter\Pdo\Mysql
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
        return \Maho\Db\Adapter\Pdo\Mysql::class;
    }
}
