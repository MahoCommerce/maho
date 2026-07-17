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

/** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
$attribute = $installer->getAttribute('catalog_product', 'weight');

if ($attribute) {
    $installer->updateAttribute(
        $attribute['entity_type_id'],
        $attribute['attribute_id'],
        'frontend_input',
        $attribute['attribute_code'],
    );
}
