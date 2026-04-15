<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_DbSchema
{
    /**
     * Get full schema for a database table including columns, indexes, and foreign keys.
     *
     * Accepts either a raw table name (e.g. "catalog_product_entity") or
     * a resource alias (e.g. "catalog/product_entity") which will be resolved
     * through Maho's table name mapping.
     */
    public function getTableSchema(string $tableName): array
    {
        $resource = Mage::getSingleton('core/resource');

        if (str_contains($tableName, '/')) {
            $resolvedName = $resource->getTableName($tableName);
        } else {
            $resolvedName = $tableName;
        }

        $adapter = $resource->getConnection('core_read');

        if (!$adapter->isTableExists($resolvedName)) {
            return ['error' => "Table '{$resolvedName}' does not exist"];
        }

        $columns = [];
        foreach ($adapter->describeTable($resolvedName) as $name => $col) {
            $columns[$name] = [
                'type' => $col['DATA_TYPE'],
                'nullable' => $col['NULLABLE'],
                'default' => $col['DEFAULT'],
                'primary' => $col['PRIMARY'],
                'identity' => $col['IDENTITY'],
                'unsigned' => $col['UNSIGNED'],
                'length' => $col['LENGTH'] ?: null,
                'precision' => $col['PRECISION'] ?: null,
                'scale' => $col['SCALE'] ?: null,
            ];
        }

        $indexes = [];
        foreach ($adapter->getIndexList($resolvedName) as $idx) {
            $indexes[$idx['KEY_NAME']] = [
                'type' => $idx['INDEX_TYPE'],
                'columns' => $idx['COLUMNS_LIST'],
            ];
        }

        $foreignKeys = [];
        foreach ($adapter->getForeignKeys($resolvedName) as $fk) {
            $foreignKeys[$fk['FK_NAME']] = [
                'column' => $fk['COLUMN_NAME'],
                'ref_table' => $fk['REF_TABLE_NAME'],
                'ref_column' => $fk['REF_COLUMN_NAME'],
                'on_delete' => $fk['ON_DELETE'],
                'on_update' => $fk['ON_UPDATE'],
            ];
        }

        return [
            'table' => $resolvedName,
            'alias' => str_contains($tableName, '/') ? $tableName : null,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }
}
