<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_ImportExport_Model_Resource_Setup $this */
$installer = $this;

$installer->getConnection()->modifyColumn(
    $installer->getTable('importexport/importdata'),
    'data',
    [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => '4G',
        'default' => '',
        'comment' => 'Data',
    ],
);
