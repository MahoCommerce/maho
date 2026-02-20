<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/coupon'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'comment'  => 'Coupon Code Creation Date',
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
        ],
    );

$installer->getConnection()->addColumn(
    $installer->getTable('salesrule/coupon'),
    'type',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'comment'  => 'Coupon Code Type',
        'default'  => 0,
    ],
);

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/rule'),
        'use_auto_generation',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'comment'  => 'Use Auto Generation',
            'nullable' => false,
            'default'  => 0,
        ],
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/rule'),
        'uses_per_coupon',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_INTEGER,
            'comment'  => 'Uses Per Coupon',
            'nullable' => false,
            'default'  => 0,
        ],
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/coupon_aggregated'),
        'rule_name',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
            'length'   => 255,
            'comment'  => 'Rule Name',
        ],
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/coupon_aggregated_order'),
        'rule_name',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
            'length'   => 255,
            'comment'  => 'Rule Name',
        ],
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('salesrule/coupon_aggregated_updated'),
        'rule_name',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
            'length'   => 255,
            'comment'  => 'Rule Name',
        ],
    );

$installer->getConnection()
    ->addIndex(
        $installer->getTable('salesrule/coupon_aggregated'),
        $installer->getIdxName(
            'salesrule/coupon_aggregated',
            ['rule_name'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ),
        ['rule_name'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
    );

$installer->getConnection()
    ->addIndex(
        $installer->getTable('salesrule/coupon_aggregated_order'),
        $installer->getIdxName(
            'salesrule/coupon_aggregated_order',
            ['rule_name'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ),
        ['rule_name'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
    );

$installer->getConnection()
    ->addIndex(
        $installer->getTable('salesrule/coupon_aggregated_updated'),
        $installer->getIdxName(
            'salesrule/coupon_aggregated_updated',
            ['rule_name'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ),
        ['rule_name'],
        Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
    );
