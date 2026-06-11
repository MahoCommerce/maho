<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE. Value is now managed explicitly in PHP via _beforeSave()
// for cross-engine parity (see issue #856).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('cataloglinkrule/rule'),
        'updated_at',
        ['default' => Table::TIMESTAMP_INIT],
    );
}

$installer->endSetup();
