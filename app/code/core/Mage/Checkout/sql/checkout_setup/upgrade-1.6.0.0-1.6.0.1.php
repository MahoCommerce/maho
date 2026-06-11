<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('checkout/agreement');
$column = 'position';

if (!$connection->tableColumnExists($table, $column)) {
    $connection->addColumn(
        $table,
        $column,
        [
            'type'      => Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'length'    => 2,
            'nullable'  => false,
            'default'   => 0,
            'comment'   => 'Agreement Position',
        ],
    );
}

$installer->endSetup();
