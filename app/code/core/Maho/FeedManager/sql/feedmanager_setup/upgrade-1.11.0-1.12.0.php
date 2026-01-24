<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('feedmanager/dynamic_rule');

// Add cases column for multi-case support
if (!$connection->tableColumnExists($tableName, 'cases')) {
    $connection->addColumn($tableName, 'cases', [
        'type' => 'text',
        'size' => 'medium',
        'nullable' => true,
        'comment' => 'JSON array of condition->output cases',
    ]);
}

// Migrate existing single-output data to cases array format
$select = $connection->select()
    ->from($tableName, ['rule_id', 'conditions_serialized', 'output_type', 'output_value', 'output_attribute'])
    ->where('cases IS NULL');

$existingRules = $connection->fetchAll($select);

foreach ($existingRules as $rule) {
    // Build the case from existing data
    $case = [
        'conditions' => null, // Will be populated from conditions_serialized
        'output_type' => $rule['output_type'] ?: 'static',
        'output_value' => $rule['output_value'],
        'output_attribute' => $rule['output_attribute'],
        'combined_position' => 'prefix', // Default for existing combined rules
        'is_default' => false,
    ];

    // Try to unserialize existing conditions
    if (!empty($rule['conditions_serialized'])) {
        // Check if it's JSON (from earlier migration) or PHP serialized
        $conditionsData = $rule['conditions_serialized'];
        if (str_starts_with($conditionsData, '{') || str_starts_with($conditionsData, '[')) {
            // It's JSON
            $case['conditions'] = json_decode($conditionsData, true);
        } else {
            // It's PHP serialized - try to unserialize safely
            try {
                $conditions = @unserialize($conditionsData, ['allowed_classes' => false]);
                if ($conditions !== false) {
                    $case['conditions'] = $conditions;
                }
            } catch (Exception $e) {
                // If unserialize fails, leave conditions as null (will always match)
            }
        }
    }

    // If no conditions, this becomes the default case
    if (empty($case['conditions'])) {
        $case['is_default'] = true;
    }

    $cases = [$case];

    $connection->update(
        $tableName,
        ['cases' => json_encode($cases)],
        ['rule_id = ?' => $rule['rule_id']],
    );
}

// Note: We keep the old columns for now for backwards compatibility
// They can be removed in a future migration after confirming everything works

$installer->endSetup();
