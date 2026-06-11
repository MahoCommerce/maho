<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('core/resource'),
    'maho_version',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length'   => 50,
        'nullable' => true,
        'comment'  => 'Maho Version',
    ],
);

$installer->endSetup();
