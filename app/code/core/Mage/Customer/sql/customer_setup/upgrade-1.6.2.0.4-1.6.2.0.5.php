<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('customer/flowpassword'))
    ->addColumn('flowpassword_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Flow password Id')
    ->addColumn('ip', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'User IP')
    ->addColumn('email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Requested email for change')
    ->addColumn('requested_date', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
        'default'  => '0000-00-00 00:00:00',
    ], 'Requested date for change')
    ->addIndex(
        $installer->getIdxName('customer/flowpassword', ['email']),
        ['email'],
    )
    ->addIndex(
        $installer->getIdxName('customer/flowpassword', ['ip']),
        ['ip'],
    )
    ->addIndex(
        $installer->getIdxName('customer/flowpassword', ['requested_date']),
        ['requested_date'],
    )
    ->setComment('Customer flow password');
$installer->getConnection()->createTable($table);

$installer->endSetup();
