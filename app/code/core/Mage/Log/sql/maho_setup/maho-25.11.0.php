<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

$connection = $installer->getConnection();

// Add indexes for better performance on visitor queries
// These indexes improve dashboard analytics queries

// Index for date range queries on first_visit_at
$connection->addIndex(
    $installer->getTable('log/visitor'),
    'IDX_FIRST_VISIT_DATE',
    ['first_visit_at', 'store_id'],
    'index',
);

// Index for date range queries on last_visit_at
$connection->addIndex(
    $installer->getTable('log/visitor'),
    'IDX_LAST_VISIT_DATE',
    ['last_visit_at'],
    'index',
);

// Index for URL lookups
$connection->addIndex(
    $installer->getTable('log/visitor'),
    'IDX_LAST_URL',
    ['last_url_id'],
    'index',
);

// Index for summary date queries
$connection->addIndex(
    $installer->getTable('log/summary_table'),
    'IDX_SUMMARY_DATE_STORE',
    ['add_date', 'store_id'],
    'index',
);

// Index for IP address lookups (new vs returning visitors)
$connection->addIndex(
    $installer->getTable('log/visitor_info'),
    'IDX_LOG_VISITOR_INFO_REMOTE_ADDR',
    ['remote_addr'],
    'index',
);

$installer->endSetup();
