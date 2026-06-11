<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;

$entitiesToAlter = [
    'quote_address',
    'order_address',
];

$attributes = [
    'vat_id' => ['type' => Maho\Db\Ddl\Table::TYPE_TEXT],
    'vat_is_valid' => ['type' => Maho\Db\Ddl\Table::TYPE_SMALLINT],
    'vat_request_id' => ['type' => Maho\Db\Ddl\Table::TYPE_TEXT],
    'vat_request_date' => ['type' => Maho\Db\Ddl\Table::TYPE_TEXT],
    'vat_request_success' => ['type' => Maho\Db\Ddl\Table::TYPE_SMALLINT],
];

foreach ($entitiesToAlter as $entityName) {
    foreach ($attributes as $attributeCode => $attributeParams) {
        $installer->addAttribute($entityName, $attributeCode, $attributeParams);
    }
}
