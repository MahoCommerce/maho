<?php

/**
 * Removes the product attributes and the attribute group of the removed recurring profiles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

// No startSetup(): it turns off the foreign keys on SQLite and PostgreSQL, and the attribute rows must cascade
$entityTypeId = (int) $installer->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);
$installer->removeAttribute($entityTypeId, 'is_recurring');
$installer->removeAttribute($entityTypeId, 'recurring_profile');

$adapter = $installer->getConnection();
$groupTable = $installer->getTable('eav/attribute_group');
$emptyGroupIds = $adapter->fetchCol(
    $adapter->select()
        ->from(['g' => $groupTable], ['attribute_group_id'])
        ->join(['s' => $installer->getTable('eav/attribute_set')], 's.attribute_set_id = g.attribute_set_id', [])
        ->joinLeft(['ea' => $installer->getTable('eav/entity_attribute')], 'ea.attribute_group_id = g.attribute_group_id', [])
        ->where('s.entity_type_id = ?', $entityTypeId)
        ->where('g.attribute_group_name = ?', 'Recurring Profile')
        ->where('ea.entity_attribute_id IS NULL'),
);
if ($emptyGroupIds !== []) {
    $adapter->delete($groupTable, ['attribute_group_id IN (?)' => $emptyGroupIds]);
}
