<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

// Postgres cannot store the legacy zero-date sentinel, so there is nothing to clean.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Pgsql) {
    return;
}

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
    $table = $installer->getTable($table);
    if (!$connection->isTableExists($table)) {
        continue;
    }
    foreach ($tableColumns as $column) {
        $connection->update($table, [$column => null], $connection->quoteIdentifier($column) . " LIKE '0000-00-00%'");
    }
}

$installer->endSetup();
