<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

// MySQL-specific data migration: re-encode legacy BIGINT IP values into the
// VARBINARY(16) columns now declared by sql/schema.php. PostgreSQL never stored
// these as integers, so no data migration is needed there.
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->update(
        $installer->getTable('log/visitor_info'),
        [
            'server_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(server_addr as UNSIGNED INT)))'),
        ],
    );

    $connection->update(
        $installer->getTable('log/visitor_info'),
        [
            'remote_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_addr as UNSIGNED INT)))'),
        ],
    );

    $connection->update(
        $installer->getTable('log/visitor_online'),
        [
            'remote_addr' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_addr as UNSIGNED INT)))'),
        ],
    );
}

$installer->endSetup();
