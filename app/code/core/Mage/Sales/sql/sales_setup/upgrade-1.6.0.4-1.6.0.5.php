<?php

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

$installer->getConnection()
    ->addColumn($installer->getTable('sales/order_item'), 'base_tax_refunded', [
        'type'    => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'comment' => 'Base Tax Refunded',
        'scale'     => 4,
        'precision' => 12,
    ]);
$installer->getConnection()
    ->addColumn($installer->getTable('sales/order_item'), 'discount_refunded', [
        'type'    => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'comment' => 'Discount Refunded',
        'scale'     => 4,
        'precision' => 12,
    ]);
$installer->getConnection()
    ->addColumn($installer->getTable('sales/order_item'), 'base_discount_refunded', [
        'type'    => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'comment' => 'Base Discount Refunded',
        'scale'     => 4,
        'precision' => 12,
    ]);
