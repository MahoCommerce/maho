<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// A form shows only the attributes of the set of the customer group. Before sets, a module could add
// an attribute to a form without a set, so put each such attribute in the default set.
$connection = $installer->getConnection();
foreach (['customer', 'customer_address'] as $entityTypeCode) {
    $entityTypeId = (int) $installer->getEntityTypeId($entityTypeCode);
    $setId = (int) $installer->getDefaultAttributeSetId($entityTypeId);
    $groupId = (int) $installer->getDefaultAttributeGroupId($entityTypeId, $setId);
    $attributeIds = $connection->fetchCol(
        $connection->select()
            ->distinct()
            ->from(['f' => $installer->getTable('customer/form_attribute')], ['attribute_id'])
            ->join(['a' => $installer->getTable('eav/attribute')], 'a.attribute_id = f.attribute_id', [])
            ->joinLeft(['ea' => $installer->getTable('eav/entity_attribute')], 'ea.attribute_id = f.attribute_id', [])
            ->where('a.entity_type_id = ?', $entityTypeId)
            ->where('ea.entity_attribute_id IS NULL'),
    );
    foreach ($attributeIds as $attributeId) {
        $installer->addAttributeToSet($entityTypeId, $setId, $groupId, (int) $attributeId);
    }
}

$installer->endSetup();
