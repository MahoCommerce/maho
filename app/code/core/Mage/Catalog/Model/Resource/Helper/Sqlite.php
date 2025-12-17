<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Helper_Sqlite extends Mage_Eav_Model_Resource_Helper_Sqlite
{
    /**
     * Returns columns for select
     *
     * @param string $tableAlias
     * @param string $eavType
     * @return string
     */
    #[\Override]
    public function attributeSelectFields($tableAlias, $eavType)
    {
        return '*';
    }

    /**
     * Compare Flat style with Describe style columns
     * If column a different - return false
     *
     * @param array $column
     * @param array $describe
     * @return bool
     */
    public function compareIndexColumnProperties($column, $describe)
    {
        $type = $column['type'];
        if (isset($column['length'])) {
            $type = sprintf('%s(%s)', $type[0], $column['length']);
        } else {
            $type = $type[0];
        }

        // SQLite has simplified type system
        $sqliteType = match (strtolower($describe['DATA_TYPE'] ?? '')) {
            'integer', 'int', 'smallint', 'tinyint', 'bigint' => 'integer',
            'real', 'float', 'double', 'decimal', 'numeric' => 'real',
            'blob' => 'blob',
            default => 'text',
        };

        $columnType = match (true) {
            str_contains($type, 'int') => 'integer',
            str_contains($type, 'float') || str_contains($type, 'decimal') || str_contains($type, 'real') => 'real',
            str_contains($type, 'blob') => 'blob',
            default => 'text',
        };

        return ($sqliteType === $columnType)
            && ($describe['DEFAULT'] == $column['default'])
            && ((bool) $describe['NULLABLE'] == (bool) $column['nullable']);
    }

    /**
     * Getting condition isNull(f1,f2) IS NOT Null
     *
     * @param string $field1
     * @param string $field2
     * @return string
     */
    public function getIsNullNotNullCondition($field1, $field2)
    {
        return sprintf('%s IS NOT NULL', $this->_getReadAdapter()->getIfNullSql($field1, $field2));
    }
}
