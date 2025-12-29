<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->changeColumn(
    $installer->getTable('widget/widget_instance_page'),
    'page_group',
    'page_group',
    [
        'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'    => 255,
    ],
);

$installer->getConnection()->changeColumn(
    $installer->getTable('widget/widget_instance_page'),
    'page_for',
    'page_for',
    [
        'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'    => 255,
    ],
);
