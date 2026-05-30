<?php

/**
 * Maho
 *
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are now nullable.
$columns = [
    'newsletter_queue' => ['queue_start_at', 'queue_finish_at'],
    'newsletter_queue_link' => ['letter_sent_at'],
    'newsletter_subscriber' => ['change_status_at'],
    'newsletter_template' => ['added_at', 'modified_at'],
];

$connection = $installer->getConnection();
foreach ($columns as $table => $tableColumns) {
    if (!$connection->isTableExists($table)) {
        continue;
    }
    foreach ($tableColumns as $column) {
        $connection->update($table, [$column => null], "`{$column}` LIKE '0000-00-00%'");
    }
}

$installer->endSetup();
