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
    ['table' => 'salesrule/rule',                'pk' => 'rule_id',        'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalogrule/rule',              'pk' => 'rule_id',        'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalog/category_dynamic_rule', 'pk' => 'rule_id',        'columns' => ['conditions_serialized']],
    ['table' => 'cataloglinkrule/rule',          'pk' => 'rule_id',        'columns' => ['source_conditions_serialized', 'target_conditions_serialized']],
    ['table' => 'customersegmentation/segment',  'pk' => 'segment_id',     'columns' => ['conditions_serialized']],
    ['table' => 'payment/restriction',           'pk' => 'restriction_id', 'columns' => ['conditions_serialized']],
];

$connection->beginTransaction();
try {
    foreach ($tables as $tableConfig) {
        try {
            $tableName = $installer->getTable($tableConfig['table']);
        } catch (Exception $e) {
            continue;
        }

        if (!$connection->isTableExists($tableName)) {
            continue;
        }

        $pk = $tableConfig['pk'];
        $select = $connection->select()->from($tableName, array_merge([$pk], $tableConfig['columns']));
        $rows = $connection->fetchAll($select);

        foreach ($rows as $row) {
            $updates = [];
            foreach ($tableConfig['columns'] as $column) {
                $value = $row[$column];
                if (empty($value)) {
                    continue;
                }

                // Skip if already valid JSON
                if (json_validate($value)) {
                    continue;
                }

                $data = @unserialize($value, ['allowed_classes' => false]);
                if (is_array($data)) {
                    $updates[$column] = $coreHelper->jsonEncode($data);
                } else {
                    throw new RuntimeException(
                        "Could not unserialize {$column} in {$tableName} row {$row[$pk]}",
                    );
                }
            }

            if (!empty($updates)) {
                $connection->update($tableName, $updates, [$pk . ' = ?' => $row[$pk]]);
            }
        }
    }
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();
    throw $e;
}

$installer->endSetup();
