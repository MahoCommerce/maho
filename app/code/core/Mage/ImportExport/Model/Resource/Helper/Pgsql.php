<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Resource_Helper_Pgsql extends Mage_Core_Model_Resource_Helper_Pgsql
{
    /**
     * Constants to be used for DB
     * PostgreSQL doesn't have packet size limits like MySQL, but we use a reasonable default
     */
    public const DB_MAX_DATA_SIZE = 10485760; // 10MB reasonable default for PostgreSQL

    /**
     * Returns maximum size of data that we can send to DB
     *
     * @return float
     */
    public function getMaxDataSize()
    {
        return self::DB_MAX_DATA_SIZE;
    }

    /**
     * Returns next autoincrement value for a table
     *
     * @param string $tableName
     * @return int
     * @throws Mage_Core_Exception
     */
    public function getNextAutoincrement($tableName)
    {
        $adapter = $this->_getReadAdapter();

        // Get the actual primary key column from table metadata
        $pkColumn = $this->_getPrimaryKeyColumn($tableName);

        // PostgreSQL: Get the next value from the sequence associated with the table's primary key
        $result = ['seq_name' => null];
        if ($pkColumn) {
            $sql = 'SELECT pg_get_serial_sequence(:table, :column) as seq_name';
            $result = $adapter->fetchRow($sql, ['table' => $tableName, 'column' => $pkColumn]);
        }

        if (empty($result['seq_name'])) {
            // Fallback: try common column names for primary key
            foreach (['entity_id', 'id', 'value_id', 'link_id', 'rule_id', 'order_id'] as $column) {
                $sql = 'SELECT pg_get_serial_sequence(:table, :column) as seq_name';
                $result = $adapter->fetchRow($sql, ['table' => $tableName, 'column' => $column]);
                if (!empty($result['seq_name'])) {
                    $pkColumn = $column;
                    break;
                }
            }
        }

        if (empty($result['seq_name'])) {
            // Fallback: try to get max + 1 from the table using the primary key column
            $column = $pkColumn ?: 'entity_id';
            $quotedTable = $adapter->quoteIdentifier($tableName);
            $quotedColumn = $adapter->quoteIdentifier($column);
            $sql = "SELECT COALESCE(MAX({$quotedColumn}), 0) + 1 as next_val FROM {$quotedTable}";
            $result = $adapter->fetchRow($sql);
            return (int) ($result['next_val'] ?? 1);
        }

        // Get next value from sequence without incrementing it
        $sql = 'SELECT last_value + CASE WHEN is_called THEN 1 ELSE 0 END as next_val FROM ' . $result['seq_name'];
        $seqResult = $adapter->fetchRow($sql);

        return (int) ($seqResult['next_val'] ?? 1);
    }

    /**
     * Get the primary key column name for a table
     *
     * @param string $tableName
     * @return string|null
     */
    protected function _getPrimaryKeyColumn($tableName)
    {
        $adapter = $this->_getReadAdapter();
        $describe = $adapter->describeTable($tableName);

        foreach ($describe as $column) {
            if (!empty($column['PRIMARY'])) {
                return $column['COLUMN_NAME'];
            }
        }

        return null;
    }
}
