<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

//Increase password field length
$installer->getConnection()->changeColumn(
    $installer->getTable('admin/user'),
    'password',
    'password',
    [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 255,
        'comment' => 'User Password',
    ],
);

$installer->endSetup();
