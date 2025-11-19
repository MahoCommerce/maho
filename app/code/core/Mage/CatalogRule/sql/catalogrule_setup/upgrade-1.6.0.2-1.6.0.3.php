<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$ruleProductTable = $installer->getTable('catalogrule/rule_product');

$columnOptions = [
    'TYPE' => Maho\Db\Ddl\Table::TYPE_TEXT,
    'LENGTH' => 32,
    'COMMENT' => 'Simple Action For Subitems',
];
$installer->getConnection()->addColumn($ruleProductTable, 'sub_simple_action', $columnOptions);

$columnOptions = [
    'TYPE' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'SCALE' => 4,
    'PRECISION' => 12,
    'NULLABLE' => false,
    'DEFAULT' => '0.0000',
    'COMMENT' => 'Discount Amount For Subitems',
];
$installer->getConnection()->addColumn($ruleProductTable, 'sub_discount_amount', $columnOptions);

$installer->endSetup();
