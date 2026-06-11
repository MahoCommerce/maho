<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// ROW_FORMAT is MySQL-specific, skip for other databases
if ($installer->getConnection() instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $installer->run("ALTER TABLE {$this->getTable('catalog/product_website')} ROW_FORMAT=DYNAMIC;");
    $installer->run("ALTER TABLE {$this->getTable('catalog/product_relation')} ROW_FORMAT=DYNAMIC;");
}

$installer->endSetup();
