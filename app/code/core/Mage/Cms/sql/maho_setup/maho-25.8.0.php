<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

$connection = $installer->getConnection();

// Add meta_robots column to cms_page table
$connection->addColumn(
    $installer->getTable('cms/page'),
    'meta_robots',
    [
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'    => 50,
        'nullable'  => true,
        'comment'   => 'Meta Robots'
    ]
);

$installer->endSetup();
