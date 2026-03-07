<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
