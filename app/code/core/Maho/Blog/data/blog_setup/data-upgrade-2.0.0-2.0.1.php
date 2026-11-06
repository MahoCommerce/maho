<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->updateEntityType('blog_category', 'attribute_model', 'blog/resource_eav_attribute');
$installer->updateEntityType('blog_category', 'additional_attribute_table', 'blog/eav_attribute');

$connection = $installer->getConnection();
$entities = [
    'blog_post' => $installer->getTable('blog/post'),
    'blog_category' => $installer->getTable('blog/category'),
];
foreach ($entities as $entityTypeCode => $table) {
    $entityTypeId = (int) $installer->getEntityTypeId($entityTypeCode);
    if (!$installer->getAttributeSet($entityTypeId, 'Default', 'attribute_set_id')) {
        $installer->addAttributeSet($entityTypeId, 'Default');
    }
    $installer->setDefaultSetToEntityType($entityTypeCode);
    $setId = (int) $installer->getAttributeSetId($entityTypeId, 'Default');

    $connection->update($table, ['attribute_set_id' => $setId], ['attribute_set_id = ?' => 0]);
    $connection->update($table, ['entity_type_id' => $entityTypeId], ['entity_type_id = ?' => 0]);
}

$installer->endSetup();
