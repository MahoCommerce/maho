<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Upgrade script: Refactor dynamic_rule table to use standard Maho rules engine
 *
 * Changes:
 * - Add conditions_serialized column (standard Maho rules format)
 * - Add output_type, output_value, output_attribute columns
 * - Migrate existing rule_data to new format (first output row only)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('feedmanager/dynamic_rule');

// Add conditions_serialized column
if (!$connection->tableColumnExists($tableName, 'conditions_serialized')) {
    $connection->addColumn($tableName, 'conditions_serialized', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => '2m',
        'nullable' => true,
        'comment' => 'Conditions Serialized (Maho Rule Format)',
    ]);
}

// Add output_type column
if (!$connection->tableColumnExists($tableName, 'output_type')) {
    $connection->addColumn($tableName, 'output_type', [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 20,
        'nullable' => false,
        'default' => 'static',
        'comment' => 'Output Type (static, attribute, combined)',
    ]);
}

// Add output_value column
if (!$connection->tableColumnExists($tableName, 'output_value')) {
    $connection->addColumn($tableName, 'output_value', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => '64k',
        'nullable' => true,
        'comment' => 'Output Static Value or Prefix',
    ]);
}

// Add output_attribute column
if (!$connection->tableColumnExists($tableName, 'output_attribute')) {
    $connection->addColumn($tableName, 'output_attribute', [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 255,
        'nullable' => true,
        'comment' => 'Output Attribute Code',
    ]);
}

// Migrate existing rule_data to new format
// Note: Old format had multiple output_rows with OR logic
// New format: single condition tree + single output
// We'll migrate the FIRST output row that has conditions (the primary match)
$select = $connection->select()
    ->from($tableName, ['rule_id', 'rule_data'])
    ->where('rule_data IS NOT NULL')
    ->where('conditions_serialized IS NULL');

$rules = $connection->fetchAll($select);

foreach ($rules as $rule) {
    $ruleData = json_decode($rule['rule_data'], true);
    if (!is_array($ruleData) || empty($ruleData['output_rows'])) {
        continue;
    }

    // Find the first row with conditions (skip default/fallback rows)
    $primaryRow = null;
    $fallbackRow = null;

    foreach ($ruleData['output_rows'] as $row) {
        if (!empty($row['conditions'])) {
            $primaryRow = $row;
            break;
        } else {
            $fallbackRow = $row;
        }
    }

    // Use primary row if found, otherwise fallback
    $outputRow = $primaryRow ?? $fallbackRow;
    if (!$outputRow) {
        continue;
    }

    // Convert old flat conditions to Maho rule tree format
    $conditionsArray = [
        'type' => 'feedmanager/rule_condition_combine',
        'attribute' => null,
        'operator' => null,
        'value' => '1', // ALL must be true
        'is_value_processed' => null,
        'aggregator' => 'all',
        'conditions' => [],
    ];

    if (!empty($outputRow['conditions'])) {
        foreach ($outputRow['conditions'] as $cond) {
            $conditionsArray['conditions'][] = [
                'type' => 'feedmanager/rule_condition_product',
                'attribute' => $cond['attribute'] ?? '',
                'operator' => $cond['operator'] ?? 'eq',
                'value' => $cond['value'] ?? '',
                'is_value_processed' => false,
            ];
        }
    }

    $connection->update($tableName, [
        'conditions_serialized' => json_encode($conditionsArray),
        'output_type' => $outputRow['output_type'] ?? 'static',
        'output_value' => $outputRow['output_value'] ?? '',
        'output_attribute' => $outputRow['output_attribute'] ?? null,
    ], ['rule_id = ?' => $rule['rule_id']]);
}

$installer->endSetup();
