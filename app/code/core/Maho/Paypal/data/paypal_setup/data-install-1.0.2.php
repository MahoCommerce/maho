<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();
$tbl  = $installer->getTable('core/config_data');

// Drop stale legacy Mage_Paypal config rows under 'paypal/...'.
// The deleted Mage_Paypal module wrote here; its keys never overlap with the new
// Maho_Paypal keys we're about to migrate in, so it's safe to clear the namespace.
$conn->delete($tbl, ['path LIKE ?' => 'paypal/%']);

// Migrate Maho_Paypal config from 'maho_paypal/...' to 'paypal/...'.
$conn->update(
    $tbl,
    ['path' => new Maho\Db\Expr("REPLACE(path, 'maho_paypal/', 'paypal/')")],
    ['path LIKE ?' => 'maho_paypal/%'],
);

$installer->endSetup();
