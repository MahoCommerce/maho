<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/** @var Maho\Db\Adapter\Pdo\Mysql $connection */
$connection  = $installer->getConnection();

$regionTable = $installer->getTable('directory/country_region');

/* Armed Forces changes based on USPS */

/* Armed Forces Middle East (AM) is now served by Armed Forces Europe (AE) */
$bind = ['code' => 'AE'];
$where = ['code = ?' => 'AM'];

$connection->update($regionTable, $bind, $where);

/* Armed Forces Canada (AC) is now served by Armed Forces Europe (AE) */
$bind = ['code' => 'AE'];
$where = ['code = ?' => 'AC'];

$connection->update($regionTable, $bind, $where);

/* Armed Forces Africa (AF) is now served by Armed Forces Europe (AE) */
$bind = ['code' => 'AE'];
$where = ['code = ?' => 'AF'];

$connection->update($regionTable, $bind, $where);

$installer->endSetup();
