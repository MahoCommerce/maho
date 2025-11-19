<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Paypal_Model_Resource_Setup $this */
$installer = $this;

$installer->getConnection()
    ->addColumn($installer->getTable('paypal/settlement_report_row'), 'store_id', [
        'type'    => Maho\Db\Ddl\Table::TYPE_TEXT,
        'comment' => 'Store ID',
        'length'  => '50']);
