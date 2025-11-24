<?php

/**
 * Maho
 *
 * @category   Mage
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
    'log_visitor',
    'IDX_FIRST_VISIT_DATE',
    ['first_visit_at', 'store_id'],
    'index',
);

// Index for date range queries on last_visit_at
$connection->addIndex(
    'log_visitor',
    'IDX_LAST_VISIT_DATE',
    ['last_visit_at'],
    'index',
);

// Index for URL lookups
$connection->addIndex(
    'log_visitor',
    'IDX_LAST_URL',
    ['last_url_id'],
    'index',
);

// Index for summary date queries
$connection->addIndex(
    'log_summary',
    'IDX_SUMMARY_DATE_STORE',
    ['add_date', 'store_id'],
    'index',
);

$installer->endSetup();
