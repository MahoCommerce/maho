<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert remaining PHP-serialized data in various tables to JSON format
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$coreHelper = Mage::helper('core');
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

$installer->endSetup();
