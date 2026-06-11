<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->changeColumn(
    $installer->getTable('sales/quote'),
    'remote_ip',
    'remote_ip',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length'   => 255,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Remote Ip',
    ],
);

$installer->endSetup();
