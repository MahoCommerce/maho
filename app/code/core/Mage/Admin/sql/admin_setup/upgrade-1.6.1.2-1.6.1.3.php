<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

//Increase password field length
$installer->getConnection()->changeColumn(
    $installer->getTable('admin/user'),
    'password',
    'password',
    [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 255,
        'comment' => 'User Password',
    ],
);

$installer->endSetup();
