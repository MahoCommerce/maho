<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$feedTable = $installer->getTable('feedmanager/feed');

// XML Builder columns
if (!$connection->tableColumnExists($feedTable, 'xml_structure')) {
    $connection->addColumn($feedTable, 'xml_structure', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'size' => '64K',
        'nullable' => true,
        'comment' => 'XML Structure Definition (JSON)',
    ]);
}

$installer->endSetup();
