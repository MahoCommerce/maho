<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$tableName = $installer->getTable('catalogrule/rule');
$columnOptions = [
    'TYPE'      => Maho\Db\Ddl\Table::TYPE_SMALLINT,
    'UNSIGNED'  => true,
    'NULLABLE'  => false,
    'DEFAULT'   => 0,
    'COMMENT'   => 'Is Rule Enable For Subitems',
];
$installer->getConnection()->addColumn($tableName, 'sub_is_enable', $columnOptions);

$columnOptions = [
    'TYPE'      => Maho\Db\Ddl\Table::TYPE_TEXT,
    'LENGTH'    => 32,
    'COMMENT'   => 'Simple Action For Subitems',
];
$installer->getConnection()->addColumn($tableName, 'sub_simple_action', $columnOptions);

$columnOptions = [
    'TYPE'      => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'SCALE'     => 4,
    'PRECISION' => 12,
    'NULLABLE'  => false,
    'DEFAULT'   => '0.0000',
    'COMMENT'   => 'Discount Amount For Subitems',
];
$installer->getConnection()->addColumn($tableName, 'sub_discount_amount', $columnOptions);
