<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
    /**
     * Apply Index module DB updates and sync indexes declaration
     *
     * @return $this
     */
    #[\Override]
    public function applyUpdates()
    {
        parent::applyUpdates();
        $this->_syncIndexes();

        return $this;
    }

    /**
     * Sync indexes declarations in config and in DB
     *
     * @return $this
     */
    protected function _syncIndexes()
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return $this;
        }
        $indexes = Mage::getConfig()->getNode(Mage_Index_Model_Process::XML_PATH_INDEXER_DATA);
        $indexCodes = [];
        foreach ($indexes->children() as $code => $index) {
            $indexCodes[] = $code;
        }
        $table = $this->getTable('index/process');
        $select = $connection->select()->from($table, 'indexer_code');
        $existingIndexes = $connection->fetchCol($select);
        $delete = array_diff($existingIndexes, $indexCodes);
        $insert = array_diff($indexCodes, $existingIndexes);

        if (!empty($delete)) {
            $connection->delete($table, $connection->quoteInto('indexer_code IN (?)', $delete));
        }
        if (!empty($insert)) {
            $insertData = [];
            foreach ($insert as $code) {
                $insertData[] = [
                    'indexer_code' => $code,
                    'status' => Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX,
                ];
            }
            if (method_exists($connection, 'insertArray')) {
                $connection->insertArray($table, ['indexer_code', 'status'], $insertData);
            }
        }

        return $this;
    }
}
