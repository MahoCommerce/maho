<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert rule conditions/actions from PHP serialize to JSON format
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$coreHelper = Mage::helper('core');

$tables = [
    ['table' => 'salesrule/rule',                'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalogrule/rule',              'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalog/category_dynamic_rule', 'columns' => ['conditions_serialized']],
    ['table' => 'cataloglinkrule/rule',          'columns' => ['source_conditions_serialized', 'target_conditions_serialized']],
    ['table' => 'customersegmentation/segment',  'columns' => ['conditions_serialized']],
    ['table' => 'payment/restriction',           'columns' => ['conditions_serialized']],
];

foreach ($tables as $tableConfig) {
    try {
        $tableName = $installer->getTable($tableConfig['table']);
    } catch (Exception $e) {
        continue;
    }

    if (!$connection->isTableExists($tableName)) {
        continue;
    }

    // Determine primary key column
    $describe = $connection->describeTable($tableName);
    $primaryKey = null;
    foreach ($describe as $columnName => $columnDef) {
        if (!empty($columnDef['PRIMARY'])) {
            $primaryKey = $columnName;
            break;
        }
    }
    if (!$primaryKey) {
        continue;
    }

    // Verify all columns exist
    $columnsExist = true;
    foreach ($tableConfig['columns'] as $column) {
        if (!isset($describe[$column])) {
            $columnsExist = false;
            break;
        }
    }
    if (!$columnsExist) {
        continue;
    }

    $select = $connection->select()->from($tableName, array_merge([$primaryKey], $tableConfig['columns']));
    $rows = $connection->fetchAll($select);

    foreach ($rows as $row) {
        $updates = [];
        foreach ($tableConfig['columns'] as $column) {
            $value = $row[$column];
            if (empty($value)) {
                continue;
            }

            // Skip if already valid JSON
            try {
                $coreHelper->jsonDecode($value);
                continue;
            } catch (JsonException $e) {
                // Not JSON, proceed with conversion
            }

            // Try to unserialize and convert to JSON
            try {
                $data = @unserialize($value, ['allowed_classes' => false]);
                if (is_array($data)) {
                    $updates[$column] = $coreHelper->jsonEncode($data);
                } else {
                    Mage::log(
                        "Migration warning: could not unserialize {$column} in {$tableName} row {$row[$primaryKey]}",
                        Mage::LOG_WARNING,
                    );
                }
            } catch (Exception $e) {
                Mage::log(
                    "Migration warning: failed to convert {$column} in {$tableName} row {$row[$primaryKey]}: {$e->getMessage()}",
                    Mage::LOG_WARNING,
                );
            }
        }

        if (!empty($updates)) {
            $connection->update($tableName, $updates, [$primaryKey . ' = ?' => $row[$primaryKey]]);
        }
    }
}

$installer->endSetup();
