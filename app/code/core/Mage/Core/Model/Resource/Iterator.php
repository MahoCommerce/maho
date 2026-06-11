<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Iterator extends \Maho\DataObject
{
    /**
     * Walk over records fetched from query one by one using callback function
     *
     * @param \Maho\Db\Statement\StatementInterface|\Maho\Db\Select|string $query
     * @param array|string $callbacks
     * @param \Maho\Db\Adapter\AdapterInterface $adapter
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
     * @param \Maho\Db\Statement\StatementInterface|\Maho\Db\Select|string $query
     * @param \Maho\Db\Adapter\AdapterInterface $conn
     * @return \Maho\Db\Statement\StatementInterface
     * @throws Mage_Core_Exception
     */
    protected function _getStatement($query, $conn = null)
    {
        if ($query instanceof \Maho\Db\Statement\StatementInterface) {
            return $query;
        }

        if ($query instanceof \Maho\Db\Select) {
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
