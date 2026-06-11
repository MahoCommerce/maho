<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'msrp_enabled',
    'source_model',
    'catalog/product_attribute_source_msrp_type_enabled',
);

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'msrp_enabled',
    'default_value',
    Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Enabled::MSRP_ENABLE_USE_CONFIG,
);

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'msrp_display_actual_price_type',
    'source_model',
    'catalog/product_attribute_source_msrp_type_price',
);

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'msrp_display_actual_price_type',
    'default_value',
    Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Price::TYPE_USE_CONFIG,
);
