<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

// Schema portion of this upgrade (catalog_product_entity_group_price,
// catalog_product_index_group_price, and group_price columns on price-indexer
// tables) is declared in sql/schema.php. Only the EAV attribute install remains.

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->addAttribute('catalog_product', 'group_price', [
    'type'                       => 'decimal',
    'label'                      => 'Group Price',
    'input'                      => 'text',
    'backend'                    => 'catalog/product_attribute_backend_groupprice',
    'required'                   => false,
    'sort_order'                 => 6,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'apply_to'                   => 'simple,configurable,virtual',
    'group'                      => 'Prices',
]);
