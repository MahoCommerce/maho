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

// Migrate Maho_Paypal config from 'maho_paypal/...' to 'paypal/...'. Legacy Mage_Paypal
// rows under paypal/... are deliberately left in place — its surviving keys (account/...,
// general/business_account, general/merchant_country, wpp/..., paypal_payment_*) and
// Maho_Paypal's renamed keys (credentials/*, general/paylater_*) live under disjoint
// sub-paths, so the UNIQUE (scope, scope_id, path) constraint can't fire on the rename.
$conn->update(
    $tbl,
    ['path' => new Maho\Db\Expr("REPLACE(path, 'maho_paypal/', 'paypal/')")],
    ['path LIKE ?' => 'maho_paypal/%'],
);

$installer->endSetup();
