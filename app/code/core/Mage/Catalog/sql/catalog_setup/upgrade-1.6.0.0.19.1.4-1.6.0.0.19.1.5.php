<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

$connection->addColumn($installer->getTable('catalog/product_attribute_group_price'), 'is_percent', [
    'type'      => Maho\Db\Ddl\Table::TYPE_SMALLINT,
    'unsigned'  => true,
    'nullable'  => false,
    'default'   => '0',
    'comment'   => 'Is Percent',
]);
