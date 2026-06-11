<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$attributeId = 'custom_layout_update';
$entitiesToUpgrade = [
    $installer->getEntityTypeId('catalog_product'),
    $installer->getEntityTypeId('catalog_category'),
];
foreach ($entitiesToUpgrade as $entityTypeId) {
    if ($this->getAttributeId($entityTypeId, $attributeId)) {
        $installer->updateAttribute(
            $entityTypeId,
            $attributeId,
            'backend_model',
            'catalog/attribute_backend_customlayoutupdate',
        );
    }
}
