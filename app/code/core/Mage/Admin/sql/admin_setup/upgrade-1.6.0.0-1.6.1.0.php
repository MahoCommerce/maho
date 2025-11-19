<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Add reset password link token column
$installer->getConnection()->addColumn($installer->getTable('admin/user'), 'rp_token', [
    'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
    'length' => 256,
    'nullable' => true,
    'default' => null,
    'comment' => 'Reset Password Link Token',
]);

// Add reset password link token creation date column
$installer->getConnection()->addColumn($installer->getTable('admin/user'), 'rp_token_created_at', [
    'type' => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
    'nullable' => true,
    'default' => null,
    'comment' => 'Reset Password Link Token Creation Date',
]);

$installer->endSetup();
