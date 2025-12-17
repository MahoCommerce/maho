<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (http://www.magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Add index to sales_flat_order on customer_email for fast lookup
// MySQL uses prefix index (15 bytes), PostgreSQL uses regular index
$keyList = $installer->getConnection()->getIndexList($installer->getTable('sales/order'));
if (!isset($keyList['IDX_SALES_FLAT_ORDER_CUSTOMER_EMAIL'])) {
    if ($installer->getConnection() instanceof Maho\Db\Adapter\Pdo\Mysql) {
        // MySQL supports prefix indexes
        $installer->run("
            ALTER TABLE {$installer->getTable('sales/order')}
            ADD INDEX `IDX_SALES_FLAT_ORDER_CUSTOMER_EMAIL` (`customer_email` (15));
        ");
    } else {
        // PostgreSQL: use regular index on the full column
        $installer->getConnection()->addIndex(
            $installer->getTable('sales/order'),
            'IDX_SALES_FLAT_ORDER_CUSTOMER_EMAIL',
            ['customer_email'],
        );
    }
}

// Add index to sales_flat_order_item.product_id for fast join/lookup
$this->getConnection()->addIndex(
    $installer->getTable('sales/order_item'),
    'IDX_SALES_FLAT_ORDER_ITEM_PRODUCT_ID',
    ['product_id'],
);

$installer->endSetup();
