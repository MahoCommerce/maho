<?php

/**
 * Points the product attributes status and visibility to source models that extend
 * Mage_Eav_Model_Entity_Attribute_Source_Abstract.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$connection = $installer->getConnection();
$sourceModels = [
    'catalog/product_status' => 'catalog/product_attribute_source_status',
    'catalog/product_visibility' => 'catalog/product_attribute_source_visibility',
];
foreach ($sourceModels as $oldSourceModel => $newSourceModel) {
    $connection->update(
        $installer->getTable('eav/attribute'),
        ['source_model' => $newSourceModel],
        [
            'entity_type_id = ?' => $installer->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY),
            'source_model = ?' => $oldSourceModel,
        ],
    );
}
