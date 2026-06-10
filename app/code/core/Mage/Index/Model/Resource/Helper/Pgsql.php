<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Resource_Helper_Pgsql extends Mage_Core_Model_Resource_Helper_Pgsql
{
    /**
     * Insert data from select statement
     *
     * @param Mage_Index_Model_Resource_Abstract $object
     * @param Maho\Db\Select $select
     * @param string $destTable
     * @param array $columns
     * @param bool $readToIndex
     * @return Mage_Index_Model_Resource_Abstract
     */
    public function insertData($object, $select, $destTable, $columns, $readToIndex)
    {
        return $object->insertFromSelect($select, $destTable, $columns, $readToIndex);
    }
}
