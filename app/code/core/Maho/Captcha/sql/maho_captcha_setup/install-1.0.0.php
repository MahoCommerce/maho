<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->newTable($installer->getTable('maho_captcha/captcha'))
    ->addColumn('challenge', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'primary' => true
    ])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => false
    ));
$installer->getConnection()->createTable($table);

$installer->endSetup();
