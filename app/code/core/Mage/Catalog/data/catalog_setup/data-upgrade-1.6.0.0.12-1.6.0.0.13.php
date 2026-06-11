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
$groupPriceAttrId = $installer->getAttribute('catalog_product', 'group_price', 'attribute_id');
$priceAttrId = $installer->getAttribute('catalog_product', 'price', 'attribute_id');
$connection = $installer->getConnection();

// update sort_order of Group Price attribute to be after Price
$select = $connection->select()
    ->join(
        ['t2' => $installer->getTable('eav/entity_attribute')],
        't1.attribute_group_id = t2.attribute_group_id',
        ['sort_order' => new Maho\Db\Expr('t2.sort_order + 1')],
    )->where('t1.attribute_id = ?', $groupPriceAttrId)
    ->where('t2.attribute_id = ?', $priceAttrId);
$query = $select->crossUpdateFromSelect(['t1' => $installer->getTable('eav/entity_attribute')]);
$connection->query($query);

// update sort_order of all other attributes to be after Group Price
$select = $connection->select()
    ->join(
        ['t2' => $installer->getTable('eav/entity_attribute')],
        't1.attribute_group_id = t2.attribute_group_id',
        ['sort_order' => new Maho\Db\Expr('t1.sort_order + 1')],
    )->where('t1.attribute_id != ?', $groupPriceAttrId)
    ->where('t1.sort_order >= t2.sort_order')
    ->where('t2.attribute_id = ?', $groupPriceAttrId);
$query = $select->crossUpdateFromSelect(['t1' => $installer->getTable('eav/entity_attribute')]);
$connection->query($query);
