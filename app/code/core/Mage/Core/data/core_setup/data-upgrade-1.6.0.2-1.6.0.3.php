<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

// Backfill crc_string for rows existing before the column was added in 1.6.0.3.
// CRC32() is MySQL-only; for other engines, compute in PHP. On a fresh install
// the table is empty and this loop is a no-op.
$translateTable = $installer->getTable('core/translate');
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->update($translateTable, [
        'crc_string' => new Maho\Db\Expr('CRC32(' . $connection->quoteIdentifier('string') . ')'),
    ]);
} else {
    $rows = $connection->fetchAll(
        $connection->select()->from($translateTable, ['key_id', 'string']),
    );
    foreach ($rows as $row) {
        $connection->update(
            $translateTable,
            ['crc_string' => crc32((string) $row['string'])],
            ['key_id = ?' => $row['key_id']],
        );
    }
}
