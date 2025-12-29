<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Helper_Pgsql extends Mage_Eav_Model_Resource_Helper_Pgsql
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
        $length = null;
        $precision = null;
        $scale = null;

        $matches = [];
        if (preg_match('/^((?:var)?char|character varying)\((\d+)\)/', $type, $matches)) {
            $type = $matches[1];
            $length = $matches[2];
        } elseif (preg_match('/^(numeric|decimal)\((\d+),(\d+)\)/', $type, $matches)) {
            $type = 'numeric';
            $precision = $matches[2];
            $scale = $matches[3];
        } elseif (preg_match('/^(real|double precision)/', $type, $matches)) {
            $type = $matches[1];
        } elseif (preg_match('/^((?:big|small)?int|integer)\s*(?:\((\d+)\))?/', $type, $matches)) {
            $type = $matches[1];
        }

        // Normalize PostgreSQL types
        $descType = $describe['DATA_TYPE'];
        if ($descType === 'int4' || $descType === 'int') {
            $descType = 'integer';
        } elseif ($descType === 'int2') {
            $descType = 'smallint';
        } elseif ($descType === 'int8') {
            $descType = 'bigint';
        } elseif ($descType === 'float4') {
            $descType = 'real';
        } elseif ($descType === 'float8') {
            $descType = 'double precision';
        }

        return ($descType == $type)
            && ($describe['DEFAULT'] == $column['default'])
            && ((bool) $describe['NULLABLE'] == (bool) $column['nullable'])
            && ($describe['LENGTH'] == $length)
            && ($describe['SCALE'] == $scale)
            && ($describe['PRECISION'] == $precision);
    }

    /**
     * Getting condition COALESCE(f1,f2) IS NOT Null
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
