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

// Create dynamic_rule table
$tableName = $installer->getTable('feedmanager/dynamic_rule');

if (!$connection->isTableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn('rule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ], 'Rule ID')
        ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Display Name')
        ->addColumn('code', Maho\Db\Ddl\Table::TYPE_VARCHAR, 100, [
            'nullable' => false,
        ], 'Unique Code')
        ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
            'nullable' => true,
        ], 'Description')
        ->addColumn('is_system', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned' => true,
            'nullable' => false,
            'default' => 0,
        ], 'Is System Rule (cannot delete)')
        ->addColumn('is_enabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned' => true,
            'nullable' => false,
            'default' => 1,
        ], 'Is Enabled')
        ->addColumn('rule_data', Maho\Db\Ddl\Table::TYPE_TEXT, '1m', [
            'nullable' => true,
        ], 'Rule Configuration JSON')
        ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
            'default' => 0,
        ], 'Sort Order')
        ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
        ], 'Created At')
        ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
        ], 'Updated At')
        ->addIndex(
            $installer->getIdxName('feedmanager/dynamic_rule', ['code'], 'unique'),
            ['code'],
            ['type' => 'unique'],
        )
        ->addIndex(
            $installer->getIdxName('feedmanager/dynamic_rule', ['is_enabled', 'sort_order']),
            ['is_enabled', 'sort_order'],
        )
        ->setComment('FeedManager Dynamic Attribute Rules');

    $connection->createTable($table);
}

// Seed default system rules
$now = Mage_Core_Model_Locale::now();

$defaultRules = [
    [
        'name' => 'Stock Status',
        'code' => 'stock_status',
        'description' => 'Returns "in_stock" or "out_of_stock" based on stock availability',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 10,
        'rule_data' => json_encode([
            'output_rows' => [
                [
                    'conditions' => [['attribute' => 'is_in_stock', 'operator' => 'eq', 'value' => '1']],
                    'output_type' => 'static',
                    'output_value' => 'in_stock',
                    'output_attribute' => null,
                ],
                [
                    'conditions' => [],
                    'output_type' => 'static',
                    'output_value' => 'out_of_stock',
                    'output_attribute' => null,
                ],
            ],
        ]),
    ],
    [
        'name' => 'Availability',
        'code' => 'availability',
        'description' => 'Returns "in stock" or "out of stock" (with spaces) for Google Shopping format',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 20,
        'rule_data' => json_encode([
            'output_rows' => [
                [
                    'conditions' => [['attribute' => 'is_in_stock', 'operator' => 'eq', 'value' => '1']],
                    'output_type' => 'static',
                    'output_value' => 'in stock',
                    'output_attribute' => null,
                ],
                [
                    'conditions' => [],
                    'output_type' => 'static',
                    'output_value' => 'out of stock',
                    'output_attribute' => null,
                ],
            ],
        ]),
    ],
    [
        'name' => 'Identifier Exists',
        'code' => 'identifier_exists',
        'description' => 'Returns "yes" if product has GTIN or MPN, "no" otherwise',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 30,
        'rule_data' => json_encode([
            'output_rows' => [
                [
                    'conditions' => [['attribute' => 'gtin', 'operator' => 'notnull', 'value' => '']],
                    'output_type' => 'static',
                    'output_value' => 'yes',
                    'output_attribute' => null,
                ],
                [
                    'conditions' => [['attribute' => 'mpn', 'operator' => 'notnull', 'value' => '']],
                    'output_type' => 'static',
                    'output_value' => 'yes',
                    'output_attribute' => null,
                ],
                [
                    'conditions' => [],
                    'output_type' => 'static',
                    'output_value' => 'no',
                    'output_attribute' => null,
                ],
            ],
        ]),
    ],
    [
        'name' => 'Sale Price',
        'code' => 'sale_price',
        'description' => 'Returns special_price if it exists and is less than regular price, otherwise empty',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 40,
        'rule_data' => json_encode([
            'output_rows' => [
                [
                    'conditions' => [
                        ['attribute' => 'special_price', 'operator' => 'notnull', 'value' => ''],
                        ['attribute' => 'special_price', 'operator' => 'lt_attr', 'value' => 'price'],
                    ],
                    'output_type' => 'attribute',
                    'output_value' => null,
                    'output_attribute' => 'special_price',
                ],
                [
                    'conditions' => [],
                    'output_type' => 'static',
                    'output_value' => '',
                    'output_attribute' => null,
                ],
            ],
        ]),
    ],
];

foreach ($defaultRules as $ruleData) {
    // Check if rule already exists
    $exists = $connection->fetchOne(
        $connection->select()
            ->from($tableName, ['rule_id'])
            ->where('code = ?', $ruleData['code']),
    );

    if (!$exists) {
        $connection->insert($tableName, array_merge($ruleData, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }
}

$installer->endSetup();
