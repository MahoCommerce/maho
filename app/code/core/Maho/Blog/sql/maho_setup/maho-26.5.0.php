<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE. Value is now managed explicitly in PHP via _beforeSave()
// for cross-engine parity (see issue #856).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $tables = [
        'blog/post',
        'blog/category',
    ];
    foreach ($tables as $alias) {
        $table = $installer->getTable($alias);
        if (!$installer->getConnection()->isTableExists($table)) {
            continue;
        }
        $installer->getConnection()->modifyColumn(
            $table,
            'updated_at',
            ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT],
        );
    }
}

$installer->endSetup();
