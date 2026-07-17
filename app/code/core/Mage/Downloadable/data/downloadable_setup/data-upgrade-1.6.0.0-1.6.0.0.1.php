<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$msrpEnabled = $installer->getAttribute('catalog_product', 'msrp_enabled', 'apply_to');
if ($msrpEnabled && !str_contains($msrpEnabled, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE)) {
    $installer->updateAttribute('catalog_product', 'msrp_enabled', [
        'apply_to'      => $msrpEnabled . ',' . Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
    ]);
}

$msrpDisplay = $installer->getAttribute('catalog_product', 'msrp_display_actual_price_type', 'apply_to');
if ($msrpDisplay && !str_contains($msrpEnabled, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE)) {
    $installer->updateAttribute('catalog_product', 'msrp_display_actual_price_type', [
        'apply_to'      => $msrpDisplay . ',' . Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
    ]);
}

$msrp = $installer->getAttribute('catalog_product', 'msrp', 'apply_to');
if ($msrp && !str_contains($msrpEnabled, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE)) {
    $installer->updateAttribute('catalog_product', 'msrp', [
        'apply_to'      => $msrp . ',' . Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
    ]);
}
