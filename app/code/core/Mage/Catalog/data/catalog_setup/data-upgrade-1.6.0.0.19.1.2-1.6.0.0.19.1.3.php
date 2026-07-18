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

$attribute = 'special_price';
$installer
    ->updateAttribute(
        Mage_Catalog_Model_Product::ENTITY,
        'special_price',
        'note',
    )
    ->updateAttribute(
        Mage_Catalog_Model_Product::ENTITY,
        'special_price',
        'frontend_class',
        'validate-special-price',
    )
;
