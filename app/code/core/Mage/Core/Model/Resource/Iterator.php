<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Iterator extends Varien_Object
{
    /**
     * Walk over records fetched from query one by one using callback function
     *
     * @param Varien_Db_Statement_Pdo_Mysql|Varien_Db_Select|string $query
     * @param array|string $callbacks
     * @param Varien_Db_Adapter_Interface $adapter
     * @return $this
     */
    public function walk($query, array $callbacks, array $args = [], $adapter = null)
    {
        $stmt = $this->_getStatement($query, $adapter);
        $args['idx'] = 0;
        while ($row = $stmt->fetch()) {
            $args['row'] = $row;
            foreach ($callbacks as $callback) {
                $result = call_user_func($callback, $args);
                if (!empty($result)) {
                    $args = array_merge($args, $result);
                }
            }
            $args['idx']++;
        }

        return $this;
    }

    /**
     * Fetch statement instance
     *
     * @param Varien_Db_Statement_Pdo_Mysql|Varien_Db_Select|string $query
     * @param Varien_Db_Adapter_Interface $conn
     * @return Varien_Db_Statement_Pdo_Mysql
     * @throws Mage_Core_Exception
     */
    protected function _getStatement($query, $conn = null)
    {
        if ($query instanceof Varien_Db_Statement_Pdo_Mysql) {
            return $query;
        }

        if ($query instanceof Varien_Db_Select) {
            return $query->query();
        }

        if (is_string($query)) {
            if (!$conn instanceof \Maho\Db\Adapter\AdapterInterface) {
                Mage::throwException(Mage::helper('core')->__('Invalid connection'));
            }
            return $conn->query($query);
        }

        Mage::throwException(Mage::helper('core')->__('Invalid query'));
    }
}
