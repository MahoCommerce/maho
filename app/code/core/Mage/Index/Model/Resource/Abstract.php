<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

abstract class Mage_Index_Model_Resource_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    public const IDX_SUFFIX = '_idx';
    public const TMP_SUFFIX = '_tmp';

    /**
     * Name of the lock that a partial reindex holds while it uses the _tmp tables
     */
    public const TMP_TABLE_LOCK = 'index_tmp_table';

    protected static bool $_holdsTmpTableLock = false;

    /**
     * Flag that defines if need to use "_idx" index table suffix instead of "_tmp"
     *
     * @var bool
     */
    protected $_isNeedUseIdxTable = false;

    /**
     * Flag that defines if need to disable keys during data inserting
     *
     * @var bool
     */
    protected $_isDisableKeys = false;

    /**
     * Run $callback while this process holds TMP_TABLE_LOCK. All processes share the _tmp tables.
     * A caller that opens a transaction around a partial reindex takes the lock before the transaction,
     * so another process cannot use the _tmp tables before the commit.
     * When $needed is false, run $callback without the lock.
     *
     * @throws RuntimeException when this process cannot acquire the lock
     */
    public static function runWithTmpTableLock(Closure $callback, bool $needed = true): void
    {
        if (!$needed || self::$_holdsTmpTableLock) {
            $callback();
            return;
        }
        $lock = Mage::getSingleton('core/lock');
        if (!$lock->acquire(self::TMP_TABLE_LOCK, true)) {
            throw new RuntimeException('Cannot acquire the lock ' . self::TMP_TABLE_LOCK . '.');
        }
        self::$_holdsTmpTableLock = true;
        try {
            $callback();
        } finally {
            self::$_holdsTmpTableLock = false;
            $lock->release(self::TMP_TABLE_LOCK);
        }
    }

    /**
     * Reindex all
     *
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function reindexAll()
    {
        $this->useIdxTable(true);
        return $this;
    }

    /**
     * Get DB adapter for index data processing
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getIndexAdapter()
    {
        return $this->_getWriteAdapter();
    }

    /**
     * Get index table name with additional suffix
     *
     * @param string $table
     * @return string
     */
    public function getIdxTable($table = null)
    {
        $suffix = self::TMP_SUFFIX;
        if ($this->_isNeedUseIdxTable) {
            $suffix = self::IDX_SUFFIX;
        }
        if ($table) {
            return $table . $suffix;
        }
        return $this->getMainTable() . $suffix;
    }

    /**
     * Synchronize data between index storage and original storage
     *
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function syncData()
    {
        $this->beginTransaction();
        try {
            /**
             * Can't use truncate because of transaction
             */
            $this->_getWriteAdapter()->delete($this->getMainTable());
            $this->insertFromTable($this->getIdxTable(), $this->getMainTable(), false);
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $this;
    }

    /**
     * Copy data from source table of read adapter to destination table of index adapter
     *
     * @param string $sourceTable
     * @param string $destTable
     * @param bool $readToIndex data migration direction (true - read=>index, false - index=>read)
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function insertFromTable($sourceTable, $destTable, $readToIndex = true)
    {
        if ($readToIndex) {
            $sourceColumns = array_keys($this->_getReadAdapter()->describeTable($sourceTable));
            $targetColumns = array_keys($this->_getReadAdapter()->describeTable($destTable));
        } else {
            $sourceColumns = array_keys($this->_getIndexAdapter()->describeTable($sourceTable));
            $targetColumns = array_keys($this->_getReadAdapter()->describeTable($destTable));
        }
        $select = $this->_getIndexAdapter()->select()->from($sourceTable, $sourceColumns);

        $helper = Mage::getResourceHelper('index');
        $helper->insertData($this, $select, $destTable, $targetColumns, $readToIndex);
        return $this;
    }

    /**
     * Insert data from select statement of read adapter to
     * destination table related with index adapter
     *
     * @param Maho\Db\Select $select
     * @param string $destTable
     * @param bool $readToIndex data migration direction (true - read=>index, false - index=>read)
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function insertFromSelect($select, $destTable, array $columns, $readToIndex = true)
    {
        if ($readToIndex) {
            $from   = $this->_getWriteAdapter();
            $to     = $this->_getIndexAdapter();
        } else {
            $from   = $this->_getIndexAdapter();
            $to     = $this->_getWriteAdapter();
        }

        if ($from === $to) {
            $query = $select->insertFromSelect($destTable, $columns);
            $to->query($query);
        } else {
            $stmt = $from->query($select);
            $data = [];
            $counter = 0;
            // Fetch as numeric array (mode 3)
            while ($row = $stmt->fetch(3)) {
                $data[] = $row;
                $counter++;
                if ($counter > 2000) {
                    $to->insertArray($destTable, $columns, $data);
                    $data = [];
                    $counter = 0;
                }
            }
            if (!empty($data)) {
                $to->insertArray($destTable, $columns, $data);
            }
        }

        return $this;
    }

    /**
     * Set or get what either "_idx" or "_tmp" suffixed temporary index table need to use
     *
     * @param bool $value
     * @return bool
     */
    public function useIdxTable($value = null)
    {
        if (!is_null($value)) {
            $this->_isNeedUseIdxTable = (bool) $value;
        }
        return $this->_isNeedUseIdxTable;
    }

    /**
     * Set or get flag that defines if need to disable keys during data inserting
     *
     * @param bool $value
     * @return bool
     */
    public function useDisableKeys($value = null)
    {
        if (!is_null($value)) {
            $this->_isDisableKeys = (bool) $value;
        }
        return $this->_isDisableKeys;
    }

    /**
     * Clean up temporary index table
     */
    public function clearTemporaryIndexTable()
    {
        $this->_getWriteAdapter()->delete($this->getIdxTable());
    }

    /**
     * Disable Main Table keys
     *
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function disableTableKeys()
    {
        if ($this->useDisableKeys()) {
            $this->_getWriteAdapter()->disableTableKeys($this->getMainTable());
        }
        return $this;
    }

    /**
     * Enable Main Table keys
     *
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function enableTableKeys()
    {
        if ($this->useDisableKeys()) {
            $this->_getWriteAdapter()->enableTableKeys($this->getMainTable());
        }
        return $this;
    }
}
