<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('admin/user'),
    'backend_locale',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length'   => 8,
        'nullable' => true,
        'comment'  => 'Backend Locale',
    ],
);

$installer->endSetup();
