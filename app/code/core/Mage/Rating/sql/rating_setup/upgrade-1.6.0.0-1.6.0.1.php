<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

// MySQL-specific data migration: re-encode legacy numeric IPs into varbinary form
// (column type change is now handled by sql/schema.php).
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->update(
        $installer->getTable('rating/rating_option_vote'),
        [
            'remote_ip_long' => new Maho\Db\Expr('UNHEX(HEX(CAST(remote_ip_long as UNSIGNED INT)))'),
        ],
    );
}

$installer->endSetup();
