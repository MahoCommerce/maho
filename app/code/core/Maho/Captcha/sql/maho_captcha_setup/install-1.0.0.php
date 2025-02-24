<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('maho_captcha/challenge'))
    ->addColumn('challenge', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable'  => false,
        'primary' => true,
    ])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ]);
$installer->getConnection()->createTable($table);

$installer->endSetup();
