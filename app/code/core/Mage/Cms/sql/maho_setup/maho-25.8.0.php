<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

$connection = $installer->getConnection();

// Add meta_robots column to cms_page table
$connection->addColumn(
    $installer->getTable('cms/page'),
    'meta_robots',
    [
        'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'    => 50,
        'nullable'  => true,
        'comment'   => 'Meta Robots',
    ],
);

$installer->endSetup();
