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

// Add price_currency_suffix column
if (!$connection->tableColumnExists($feedTable, 'price_currency_suffix')) {
    $connection->addColumn($feedTable, 'price_currency_suffix', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => false,
        'default' => 1,
        'comment' => 'Append currency code to prices (1=yes, 0=no)',
    ]);
}

$installer->endSetup();
