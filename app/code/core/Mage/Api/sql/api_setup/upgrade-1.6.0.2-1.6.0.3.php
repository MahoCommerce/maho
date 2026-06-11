<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$this->getConnection()->changeColumn(
    $this->getTable('api/user'),
    'lognum',
    'lognum',
    [
        'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => false,
        'default' => '0',
        'comment' => 'Quantity of log ins',
    ],
);

$this->endSetup();
