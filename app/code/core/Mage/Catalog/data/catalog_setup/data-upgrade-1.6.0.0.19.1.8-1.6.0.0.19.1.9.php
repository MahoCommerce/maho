<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Dynamic category rule conditions are now provided by Mage_Catalog (catalog/rule_condition_*)
// instead of Mage_CatalogRule, so the feature no longer requires that module. Rewrite the type
// prefix in existing serialized rules so they keep loading when Mage_CatalogRule is disabled.
$connection = $installer->getConnection();
$table = $installer->getTable('catalog/category_dynamic_rule');

$rows = $connection->fetchPairs(
    $connection->select()->from($table, ['rule_id', 'conditions_serialized']),
);

foreach ($rows as $ruleId => $serialized) {
    if ($serialized === null
        || (!str_contains($serialized, 'catalogrule/rule_condition_')
            && !str_contains($serialized, 'catalogrule\\/rule_condition_'))
    ) {
        continue;
    }

    $updated = str_replace(
        ['catalogrule/rule_condition_', 'catalogrule\\/rule_condition_'],
        ['catalog/rule_condition_', 'catalog\\/rule_condition_'],
        $serialized,
    );

    $connection->update($table, ['conditions_serialized' => $updated], ['rule_id = ?' => $ruleId]);
}

$installer->endSetup();
