<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE (#856). Value is now managed via PHP _beforeSave for cross-engine parity.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('catalog/category_dynamic_rule'),
        'updated_at',
        ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT],
    );
}

$installer->endSetup();
