<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Resource_Helper_Mysql extends Mage_Core_Model_Resource_Helper_Mysql
{
    /**
     * Constants to be used for DB
     */
    public const DB_MAX_PACKET_SIZE        = 1048576; // Maximal packet length by default in MySQL
    public const DB_MAX_PACKET_COEFFICIENT = 0.85; // The coefficient of useful data from maximum packet length

    /**
     * Semaphore to disable schema stats only once
     *
     * @var bool
     */
    private static $instantInformationSchemaStatsExpiry = false;

    /**
     * Returns maximum size of packet, that we can send to DB
     *
     * @return float
     */
    public function getMaxDataSize()
    {
        $maxPacketData = $this->_getReadAdapter()->fetchRow('SHOW VARIABLES LIKE "max_allowed_packet"');
        $maxPacket = empty($maxPacketData['Value']) ? self::DB_MAX_PACKET_SIZE : $maxPacketData['Value'];
        return floor($maxPacket * self::DB_MAX_PACKET_COEFFICIENT);
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
        $this->setInformationSchemaStatsExpiry();
        $entityStatus = $adapter->showTableStatus($tableName);

        if (empty($entityStatus) || !is_array($entityStatus)) {
            Mage::throwException(Mage::helper('importexport')->__('Cannot get table status for %s', $tableName));
        }

        // Handle both Auto_increment and auto_increment cases (PDO/Doctrine DBAL may differ)
        // Also handle case where all keys might be lowercase
        $autoIncrement = null;
        $keyFound = false;
        foreach ($entityStatus as $key => $value) {
            if (strtolower($key) === 'auto_increment') {
                $autoIncrement = $value;
                $keyFound = true;
                break;
            }
        }

        if (!$keyFound) {
            Mage::throwException(Mage::helper('importexport')->__('Auto_increment column not found for table %s', $tableName));
        }

        // If auto_increment is NULL (table has no AUTO_INCREMENT column) or 0 (empty table), return 1
        // Otherwise return the actual value
        if ($autoIncrement === null || $autoIncrement === 'NULL') {
            return 1;
        }

        return $autoIncrement > 0 ? (int) $autoIncrement : 1;
    }

    /**
     * Set information_schema_stats_expiry to 0 if not already set.
     */
    public function setInformationSchemaStatsExpiry(): void
    {
        if (!self::$instantInformationSchemaStatsExpiry) {
            try {
                $this->_getReadAdapter()->query('SET information_schema_stats_expiry = 0;');
            } catch (Exception $e) {
            }
            self::$instantInformationSchemaStatsExpiry = true;
        }
    }
}
