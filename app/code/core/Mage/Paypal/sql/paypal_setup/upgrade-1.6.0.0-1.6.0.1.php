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

/**
 * Create table 'paypal/payment_transaction'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('paypal/payment_transaction'))
    ->addColumn('transaction_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Entity Id')
    ->addColumn('txn_id', Maho\Db\Ddl\Table::TYPE_TEXT, 100, [
    ], 'Txn Id')
    ->addColumn('additional_information', Maho\Db\Ddl\Table::TYPE_BLOB, '64K', [
    ], 'Additional Information')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
    ], 'Created At')
    ->addIndex(
        $installer->getIdxName(
            'paypal/payment_transaction',
            ['txn_id'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['txn_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('PayPal Payflow Link Payment Transaction');
$installer->getConnection()->createTable($table);
