<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$this->getConnection()->changeColumn(
    $this->getTable('api/user'),
    'lognum',
    'lognum',
    [
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => false,
        'default' => '0',
        'comment' => 'Quantity of log ins',
    ],
);

$this->endSetup();
