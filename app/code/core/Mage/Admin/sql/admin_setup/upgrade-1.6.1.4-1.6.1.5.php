<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('admin/user'),
    'backend_locale',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length'   => 8,
        'nullable' => true,
        'comment'  => 'Backend Locale',
    ],
);

$installer->endSetup();
