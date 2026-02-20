<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Resource_Helper_Sqlite extends Mage_Core_Model_Resource_Helper_Sqlite
{
    /**
     * Constants to be used for DB
     * SQLite has a default page size of 4096 bytes and doesn't have the same packet limitations as MySQL
     */
    public const DB_MAX_PACKET_SIZE        = 1048576;
    public const DB_MAX_PACKET_COEFFICIENT = 0.85;

    /**
     * Returns maximum size of packet, that we can send to DB
     * SQLite doesn't have the same limitations as MySQL
     *
     * @return float
     */
    public function getMaxDataSize()
    {
        return floor(self::DB_MAX_PACKET_SIZE * self::DB_MAX_PACKET_COEFFICIENT);
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

        // In SQLite, the next autoincrement value can be found in sqlite_sequence
        // If the table doesn't exist there, it means no rows have been inserted yet
        try {
            $result = $adapter->fetchOne(
                'SELECT seq FROM sqlite_sequence WHERE name = ?',
                [$tableName],
            );

            if ($result !== false) {
                return (int) $result + 1;
            }
        } catch (\Exception $e) {
            // sqlite_sequence might not exist if AUTOINCREMENT hasn't been used
        }

        // Fallback: get max ID + 1 from the table itself
        $entityStatus = $adapter->showTableStatus($tableName);
        if (empty($entityStatus)) {
            Mage::throwException(Mage::helper('importexport')->__('Cannot get table status for %s', $tableName));
        }

        // Return 1 for empty tables
        return 1;
    }

    /**
     * Set information_schema_stats_expiry to 0 if not already set.
     * Not applicable for SQLite.
     */
    public function setInformationSchemaStatsExpiry(): void
    {
        // SQLite doesn't have information_schema_stats_expiry
    }
}
