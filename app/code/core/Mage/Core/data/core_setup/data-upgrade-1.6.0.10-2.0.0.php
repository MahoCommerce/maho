<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * Config and data normalization: log level rename, PHP serialize → JSON conversions, and
 * legacy zero-date sentinel cleanup.
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$coreHelper = Mage::helper('core');
$configTable = $installer->getTable('core/config_data');

// 1. Migrate dev/log/max_level (Zend_Log levels 0-7) to dev/log/min_level (Monolog levels).
$select = $connection->select()
    ->from($configTable)
    ->where('path = ?', 'dev/log/max_level');

foreach ($connection->fetchAll($select) as $config) {
    $oldValue = (int) $config['value'];
    $newValue = match ($oldValue) {
        0 => 600, // Emergency (was EMERG)
        1 => 550, // Alert
        2 => 500, // Critical (was CRIT)
        3 => 400, // Error (was ERR)
        4 => 300, // Warning (was WARN)
        5 => 250, // Notice
        6 => 200, // Info
        7 => 100, // Debug
        default => 100, // Default to Debug
    };

    $connection->insertOnDuplicate(
        $configTable,
        [
            'scope' => $config['scope'],
            'scope_id' => $config['scope_id'],
            'path' => 'dev/log/min_level',
            'value' => $newValue,
        ],
        ['value'],
    );
}

$connection->delete($configTable, ['path = ?' => 'dev/log/max_level']);

// 2. Convert rule conditions/actions from PHP serialize to JSON. Rules are few and critical, so an
//    unconvertible value aborts the migration loudly (the bulk passes below skip instead).
$ruleTables = [
    ['table' => 'salesrule/rule',                'pk' => 'rule_id',        'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalogrule/rule',              'pk' => 'rule_id',        'columns' => ['conditions_serialized', 'actions_serialized']],
    ['table' => 'catalog/category_dynamic_rule', 'pk' => 'rule_id',        'columns' => ['conditions_serialized']],
    ['table' => 'cataloglinkrule/rule',          'pk' => 'rule_id',        'columns' => ['source_conditions_serialized', 'target_conditions_serialized']],
    ['table' => 'customersegmentation/segment',  'pk' => 'segment_id',     'columns' => ['conditions_serialized']],
    ['table' => 'payment/restriction',           'pk' => 'restriction_id', 'columns' => ['conditions_serialized']],
];

$connection->beginTransaction();
try {
    foreach ($ruleTables as $tableConfig) {
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

// 3. Convert remaining PHP-serialized data in various tables to JSON.
$batchSize = 1000;

/**
 * Category A: Tables where the column value is always serialized.
 * Non-empty, non-JSON values are expected to be PHP serialized arrays.
 */
$alwaysSerializedTables = [
    ['table' => 'sales_flat_order_payment',             'pk' => 'entity_id',      'columns' => ['additional_information']],
    ['table' => 'sales_flat_quote_payment',             'pk' => 'payment_id',     'columns' => ['additional_information']],
    ['table' => 'sales_flat_order_payment_transaction', 'pk' => 'transaction_id', 'columns' => ['additional_information']],
    ['table' => 'sales_recurring_profile',              'pk' => 'profile_id',     'columns' => ['profile_vendor_info', 'additional_info', 'order_info', 'order_item_info', 'billing_address_info', 'shipping_address_info']],
    ['table' => 'sales_flat_order_item',                'pk' => 'item_id',        'columns' => ['product_options', 'weee_tax_applied']],
    ['table' => 'eav_attribute',                        'pk' => 'attribute_id',   'columns' => ['validate_rules']],
    ['table' => 'widget_instance',                      'pk' => 'instance_id',    'columns' => ['widget_parameters']],
    ['table' => 'core_flag',                            'pk' => 'flag_id',        'columns' => ['flag_data']],
    ['table' => 'admin_user',                           'pk' => 'user_id',        'columns' => ['extra']],
    ['table' => 'sales_flat_quote_address',             'pk' => 'address_id',     'columns' => ['applied_taxes']],
    ['table' => 'sales_flat_order_shipment',            'pk' => 'entity_id',      'columns' => ['packages']],
    ['table' => 'index_event',                          'pk' => 'event_id',       'columns' => ['new_data']],
    ['table' => 'core_email_queue',                     'pk' => 'message_id',     'columns' => ['message_parameters']],
];

/**
 * Category B: Tables where the column may or may not contain serialized data.
 * Use regex detection; skip values that don't match or fail to unserialize.
 */
$maybeSerializedTables = [
    ['table' => 'sales_flat_quote_item_option', 'pk' => 'option_id',  'column' => 'value'],
    ['table' => 'wishlist_item_option',         'pk' => 'option_id',  'column' => 'value'],
    ['table' => 'core_config_data',             'pk' => 'config_id',  'column' => 'value'],
];

// Category A: always-serialized columns
foreach ($alwaysSerializedTables as $tableConfig) {
    $tableName = $installer->getTable($tableConfig['table']);

    if (!$connection->isTableExists($tableName)) {
        continue;
    }

    // Filter out columns that don't exist in this table
    $tableColumns = $connection->describeTable($tableName);
    $columns = array_filter($tableConfig['columns'], fn($col) => isset($tableColumns[$col]));
    if (empty($columns)) {
        continue;
    }

    $pk = $tableConfig['pk'];
    $offset = 0;

    $connection->beginTransaction();
    try {
        do {
            $select = $connection->select()
                ->from($tableName, array_merge([$pk], $columns))
                ->limit($batchSize, $offset);
            $rows = $connection->fetchAll($select);

            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $column) {
                    $value = $row[$column];
                    if (empty($value)) {
                        continue;
                    }

                    // Skip if already valid JSON
                    if (json_validate($value)) {
                        continue;
                    }

                    $data = @unserialize($value, ['allowed_classes' => false]);
                    if ($data !== false || $value === 'b:0;') {
                        $updates[$column] = $coreHelper->jsonEncode($data);
                    } else {
                        Mage::log(
                            "Could not unserialize {$column} in {$tableName} row {$row[$pk]}, skipping",
                            Mage::LOG_WARNING,
                        );
                    }
                }

                if (!empty($updates)) {
                    $connection->update($tableName, $updates, [$pk . ' = ?' => $row[$pk]]);
                }
            }

            $offset += $batchSize;
        } while (count($rows) === $batchSize);

        $connection->commit();
    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
}

// Category B: maybe-serialized columns
foreach ($maybeSerializedTables as $tableConfig) {
    $tableName = $installer->getTable($tableConfig['table']);

    if (!$connection->isTableExists($tableName)) {
        continue;
    }

    $pk = $tableConfig['pk'];
    $column = $tableConfig['column'];
    $tableColumns = $connection->describeTable($tableName);
    if (!isset($tableColumns[$column])) {
        continue;
    }

    $offset = 0;

    $connection->beginTransaction();
    try {
        do {
            $select = $connection->select()
                ->from($tableName, [$pk, $column])
                ->limit($batchSize, $offset);
            $rows = $connection->fetchAll($select);

            foreach ($rows as $row) {
                $value = $row[$column];
                if (empty($value)) {
                    continue;
                }

                // Skip if already valid JSON
                if (json_validate($value)) {
                    continue;
                }

                // Only attempt conversion if it looks like a PHP serialized array
                if (!preg_match('/^a:\d+:\{/', $value)) {
                    continue;
                }

                $data = @unserialize($value, ['allowed_classes' => false]);
                if (!is_array($data)) {
                    // Not a valid serialized array, skip silently
                    continue;
                }

                $connection->update($tableName, [$column => $coreHelper->jsonEncode($data)], [$pk . ' = ?' => $row[$pk]]);
            }

            $offset += $batchSize;
        } while (count($rows) === $batchSize);

        $connection->commit();
    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
}

// 4. Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are
//    now nullable. MySQL/MariaDB only: Postgres cannot store the sentinel, so there is nothing to clean.
if (!($connection instanceof \Maho\Db\Adapter\Pdo\Pgsql)) {
    $columns = [
        'core_config_data' => ['updated_at'],
        'core_email_queue' => ['created_at', 'processed_at'],
        'core_email_template' => ['added_at', 'modified_at'],
        'design_change' => ['date_from', 'date_to'],
    ];
    foreach ($columns as $table => $tableColumns) {
        $table = $installer->getTable($table);
        if (!$connection->isTableExists($table)) {
            continue;
        }
        foreach ($tableColumns as $column) {
            $connection->update($table, [$column => null], $connection->quoteIdentifier($column) . " LIKE '0000-00-00%'");
        }
    }
}

$installer->endSetup();
