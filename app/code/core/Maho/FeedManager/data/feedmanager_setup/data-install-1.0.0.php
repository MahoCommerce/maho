<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$dynamicRuleTable = $installer->getTable('feedmanager/dynamic_rule');
$now = Mage::app()->getLocale()->nowUtc();

$defaultRules = [
    [
        'name' => 'Stock Status',
        'code' => 'stock_status',
        'description' => 'Returns "in_stock" or "out_of_stock" based on stock availability',
        'is_system' => 1,
        'is_enabled' => 1,
        'sort_order' => 10,
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'is_in_stock',
                            'operator' => 'eq',
                            'value' => '1',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'in_stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'out_of_stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
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
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'is_in_stock',
                            'operator' => 'eq',
                            'value' => '1',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'in stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'out of stock',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
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
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'any',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'gtin',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'mpn',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'static',
                'output_value' => 'yes',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => 'no',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
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
        'cases' => Mage::helper('core')->jsonEncode([
            [
                'conditions' => [
                    'type' => 'feedmanager/rule_condition_combine',
                    'attribute' => null,
                    'operator' => null,
                    'value' => '1',
                    'is_value_processed' => null,
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'special_price',
                            'operator' => 'notnull',
                            'value' => '',
                            'is_value_processed' => false,
                        ],
                        [
                            'type' => 'feedmanager/rule_condition_product',
                            'attribute' => 'special_price',
                            'operator' => 'lt_attr',
                            'value' => 'price',
                            'is_value_processed' => false,
                        ],
                    ],
                ],
                'output_type' => 'attribute',
                'output_value' => null,
                'output_attribute' => 'special_price',
                'combined_position' => 'prefix',
                'is_default' => false,
            ],
            [
                'conditions' => null,
                'output_type' => 'static',
                'output_value' => '',
                'output_attribute' => null,
                'combined_position' => 'prefix',
                'is_default' => true,
            ],
        ]),
    ],
];

foreach ($defaultRules as $ruleData) {
    $connection->insert($dynamicRuleTable, array_merge($ruleData, [
        'created_at' => $now,
        'updated_at' => $now,
    ]));
}

$installer->endSetup();
